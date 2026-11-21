<?php

namespace App\Graphs\InfluxDB;

use App\Data\Graphing\DataSeries;
use App\Data\Graphing\Formula;
use App\Data\Graphing\SeriesSum;
use App\Data\TimeSeries\MetricIdentity;
use App\Data\TimeSeries\SeriesData;
use App\Graphs\SeriesReader;
use Carbon\CarbonImmutable;
use InfluxDB2\QueryApi;

/**
 * Reads the points the poller sends to InfluxDBv2, consolidated to intervals like rrdtool does, in one query.
 * InfluxDB stores the polled values: counters are converted to rates here, which rrdtool did when they were stored.
 */
class InfluxSeriesReader implements SeriesReader
{
    private const int MIN_POINTS = 10;

    /**
     * Stored measurements whose fields are counters, stored in rrd as DERIVE or COUNTER.
     * Only the rrd definitions written by the poller know this.
     */
    private const array COUNTERS = ['ports', 'netstats-ip', 'vm.diskio', 'vm.diskops'];

    /** @var array<string, string> */
    private const array AGGREGATES = ['avg' => 'mean', 'min' => 'min', 'max' => 'max'];

    public function __construct(
        private readonly QueryApi $query,
        private readonly InfluxSeriesLocator $locator,
        private readonly string $bucket,
        private readonly int $pollInterval,
    ) {
    }

    public function read(array $series, int $from, int $to, int $points): SeriesData
    {
        $step = $this->step($from, $to, $points);
        // each point is the end of the interval, as rrdtool the intervals that hold $from and $to are included
        $first = intdiv($from, $step) * $step + $step;
        $last = (int) ceil($to / $step) * $step;
        $timestamps = range($first, max($first, $last), $step);

        // the fields each metric is read for, by consolidation
        $metrics = [];
        $metricOf = [];
        foreach ($series as $key => $item) {
            if (! $item instanceof DataSeries) {
                continue;
            }

            $id = $this->metricKey($item->metric);
            $metricOf[$key] = $id;
            $metrics[$id] ??= ['metric' => $item->metric, 'avg' => [], 'min' => [], 'max' => []];

            if ($item->field instanceof Formula) {
                // the lowest and highest of a formula are those of its average values
                array_push($metrics[$id]['avg'], ...$item->field->fields());
                continue;
            }

            $metrics[$id]['avg'][] = $item->field;
            $metrics[$id]['min'][] = $item->minField ?? $item->field;
            $metrics[$id]['max'][] = $item->maxField ?? $item->field;
        }

        if ($metrics === []) {
            return new SeriesData;
        }

        $values = $this->query($metrics, $first - 2 * $step, $last, $step, $timestamps);

        $read = [];
        foreach ($series as $key => $item) {
            if ($item instanceof SeriesSum) {
                $members = array_values(array_filter($item->series, fn (string $member) => isset($read[$member])));
                if ($members === []) {
                    continue;
                }

                $read[$key] = ['avg' => $this->sum(array_map(fn (string $member) => $read[$member]['avg'], $members)), 'min' => null, 'max' => null];
                continue;
            }

            $stored = $values[$metricOf[$key]] ?? null;
            if ($stored === null) {
                continue;
            }

            $transform = fn (?float $value) => $value === null ? null : $value * $item->scale + $item->offset;

            if ($item->field instanceof Formula) {
                $formula = $item->field;
                $avg = [];
                foreach (array_keys($timestamps) as $index) {
                    $fields = [];
                    foreach ($formula->fields() as $field) {
                        $fields[$field] = $stored['avg'][$field][$index] ?? null;
                    }
                    $avg[] = $transform($formula->evaluate($fields));
                }

                $read[$key] = ['avg' => $avg, 'min' => null, 'max' => null];
                continue;
            }

            $avg = array_map($transform, $stored['avg'][$item->field]);
            $min = array_map($transform, $stored['min'][$item->minField ?? $item->field]);
            $max = array_map($transform, $stored['max'][$item->maxField ?? $item->field]);

            $read[$key] = [
                'avg' => $avg,
                'min' => $min === $avg ? null : $min,
                'max' => $max === $avg ? null : $max,
            ];
        }

        return new SeriesData($timestamps, $read);
    }

    /**
     * Seconds of each point, at least the poll interval so each has a poll, and a whole number of them
     */
    private function step(int $from, int $to, int $points): int
    {
        $step = (int) ceil(max(1, $to - $from) / max(self::MIN_POINTS, $points));

        return max(1, (int) ceil($step / $this->pollInterval)) * $this->pollInterval;
    }

    /**
     * One query reads all metrics, a result for each metric and consolidation
     *
     * @param  array<string, array{metric: MetricIdentity, avg: list<string>, min: list<string>, max: list<string>}>  $metrics
     * @param  list<int>  $timestamps
     * @return array<string, array<string, array<string, list<float|null>>>> values of each metric by consolidation and field, metrics without data are left out
     */
    private function query(array $metrics, int $start, int $stop, int $step, array $timestamps): array
    {
        $flux = [];
        $index = 0;
        $names = [];
        foreach ($metrics as $id => $request) {
            $name = 'm' . $index++;
            $names[$name] = $id;
            $flux[] = $this->metricQuery($name, $request, $start, $stop, $step);
        }

        $positions = array_flip($timestamps);
        $values = [];
        foreach ($this->query->query(implode("\n", $flux)) ?? [] as $table) {
            foreach ($table->records as $record) {
                [$name, $consolidation] = explode('_', (string) $record->values['result'], 2);
                $time = CarbonImmutable::parse($record->getTime())->getTimestamp();
                if (! isset($positions[$time], $names[$name])) {
                    continue;
                }

                $field = (string) $record->getField();
                $value = $record->getValue();
                $values[$names[$name]][$consolidation][$field] ??= array_fill(0, count($timestamps), null);
                $values[$names[$name]][$consolidation][$field][$positions[$time]] = is_numeric($value) ? (float) $value : null;
            }
        }

        // every field the series read, missing fields have no data
        $empty = array_fill(0, count($timestamps), null);
        foreach ($values as $id => $consolidations) {
            foreach (array_keys(self::AGGREGATES) as $consolidation) {
                foreach (array_unique($metrics[$id][$consolidation]) as $field) {
                    $values[$id][$consolidation][$field] = $this->fillPollGaps($consolidations[$consolidation][$field] ?? $empty, $step);
                }
            }
        }

        return $values;
    }

    /**
     * @param  array{metric: MetricIdentity, avg: list<string>, min: list<string>, max: list<string>}  $request
     */
    private function metricQuery(string $name, array $request, int $start, int $stop, int $step): string
    {
        $names = $this->locator->locate($request['metric']);
        $series = implode(' or ', array_map(fn (MetricIdentity $stored) => '(' . implode(' and ', [
            'r._measurement == ' . $this->string($stored->measurement),
            ...array_map(fn (string $tag, $value) => 'r[' . $this->string($tag) . '] == ' . $this->string((string) $value), array_keys($stored->tags), $stored->tags),
        ]) . ')', $names));

        $fields = array_values(array_unique([...$request['avg'], ...$request['min'], ...$request['max']]));
        $isCounter = array_any($names, fn (MetricIdentity $stored) => in_array($stored->measurement, self::COUNTERS, true));

        $query = "{$name} = from(bucket: {$this->string($this->bucket)})\n"
            . '  |> range(start: ' . $this->time($start) . ', stop: ' . $this->time($stop) . ")\n"
            . "  |> filter(fn: (r) => $series)\n"
            . '  |> filter(fn: (r) => contains(value: r._field, set: [' . implode(', ', array_map($this->string(...), $fields)) . "]))\n"
            . '  |> toFloat()' . "\n";

        if ($isCounter) {
            // as rrd DERIVE with a minimum of 0: a counter that went back has no rate
            $query .= "  |> derivative(unit: 1s, nonNegative: false)\n  |> filter(fn: (r) => r._value >= 0.0)\n";
        }

        // series stored under another name, or whose descriptive tags changed, are one series
        $query .= "  |> group(columns: [\"_field\"])\n";

        foreach (self::AGGREGATES as $consolidation => $function) {
            if ($request[$consolidation] === []) {
                continue;
            }

            $set = implode(', ', array_map($this->string(...), array_values(array_unique($request[$consolidation]))));
            $query .= "$name |> filter(fn: (r) => contains(value: r._field, set: [$set]))"
                . " |> aggregateWindow(every: {$step}s, fn: $function, createEmpty: false)"
                . " |> yield(name: \"{$name}_$consolidation\")\n";
        }

        return $query;
    }

    /**
     * Polls are not exactly one interval apart, an interval may have none when its neighbours do.
     * Like the rrd heartbeat, the next poll covers it.
     *
     * @param  list<float|null>  $values
     * @return list<float|null>
     */
    private function fillPollGaps(array $values, int $step): array
    {
        if ($step > $this->pollInterval) {
            return $values;
        }

        for ($i = 1; $i < count($values) - 1; $i++) {
            if ($values[$i] === null && $values[$i - 1] !== null && $values[$i + 1] !== null) {
                $values[$i] = $values[$i + 1];
            }
        }

        return $values;
    }

    /**
     * Series without a value at a time count as zero, the sum has no value when none of them do
     *
     * @param  list<list<float|null>>  $members
     * @return list<float|null>
     */
    private function sum(array $members): array
    {
        $sum = [];
        foreach (array_keys($members[0]) as $index) {
            $values = array_filter(array_column($members, $index), fn (?float $value) => $value !== null);
            $sum[] = $values === [] ? null : array_sum($values);
        }

        return $sum;
    }

    private function metricKey(MetricIdentity $metric): string
    {
        return $metric->measurement . json_encode($metric->tags);
    }

    /**
     * A flux string literal, which would interpolate ${}
     */
    private function string(string $value): string
    {
        return '"' . strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']) . '"';
    }

    private function time(int $timestamp): string
    {
        return CarbonImmutable::createFromTimestampUTC($timestamp)->format('Y-m-d\TH:i:s\Z');
    }
}

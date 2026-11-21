<?php

namespace LibreNMS\Tests\Unit\Graphs\InfluxDB;

use App\Data\Graphing\DataSeries;
use App\Data\Graphing\Formula;
use App\Data\Graphing\SeriesSum;
use App\Data\TimeSeries\MetricIdentity;
use App\Facades\LibrenmsConfig;
use App\Graphs\InfluxDB\InfluxSeriesLocator;
use App\Graphs\InfluxDB\InfluxSeriesReader;
use App\Graphs\Rrd\RrdSeriesReader;
use App\Graphs\SeriesReader;
use Carbon\CarbonImmutable;
use InfluxDB2\FluxRecord;
use InfluxDB2\FluxTable;
use InfluxDB2\QueryApi;
use LibreNMS\Tests\TestCase;
use Mockery;

class InfluxSeriesReaderTest extends TestCase
{
    private MetricIdentity $metric;
    private MetricIdentity $missing;
    private QueryApi&Mockery\MockInterface $query;
    private InfluxSeriesLocator&Mockery\MockInterface $locator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metric = new MetricIdentity('traffic', ['hostname' => 'localhost']);
        $this->missing = new MetricIdentity('missing', ['hostname' => 'localhost']);
        $this->query = Mockery::mock(QueryApi::class);
        $this->locator = Mockery::mock(InfluxSeriesLocator::class);
        $this->locator->shouldReceive('locate')->andReturnUsing(fn (MetricIdentity $metric) => [$metric]);
    }

    public function test_it_reads_the_average_min_and_max_of_each_series_at_the_end_of_each_interval(): void
    {
        $this->query->shouldReceive('query')->once()->andReturn($this->tables([
            ['m0_avg', 1200, 'in', 2.0], ['m0_avg', 1800, 'in', 4.0], ['m0_avg', 1200, 'out', 20.0],
            ['m0_min', 1200, 'in', 1.0], ['m0_min', 1800, 'in', 3.0], ['m0_min', 1200, 'out', 10.0],
            ['m0_max', 1200, 'in', 3.0], ['m0_max', 1800, 'in', 5.0], ['m0_max', 1200, 'out', 30.0],
            ['m0_avg', 900, 'in', 9.0], // before the first interval, read for the rate of a counter
        ]));

        $data = $this->reader()->read([
            'in' => new DataSeries($this->metric, 'in', 'In'),
            'gone' => new DataSeries($this->missing, 'in', 'Gone'),
            'out' => new DataSeries($this->metric, 'out', 'Out'),
        ], 1000, 1900, 500);

        $this->assertSame([1200, 1500, 1800, 2100], $data->timestamps, 'the intervals holding the start and end are included');
        $this->assertSame(['in', 'out'], array_keys($data->series), 'series without data are left out');
        $this->assertSame([2.0, 4.0, 4.0, null], $data->series['in']['avg'], 'a missed poll between two is covered by the next');
        $this->assertSame([1.0, 3.0, 3.0, null], $data->series['in']['min']);
        $this->assertSame([3.0, 5.0, 5.0, null], $data->series['in']['max']);
        $this->assertSame([20.0, null, null, null], $data->series['out']['avg']);
    }

    public function test_one_query_reads_each_metric_from_its_stored_names(): void
    {
        $this->locator = Mockery::mock(InfluxSeriesLocator::class);
        $this->locator->shouldReceive('locate')->with($this->metric)->andReturn([
            new MetricIdentity('port', ['hostname' => 'localhost', 'id' => '7']),
            new MetricIdentity('ports', ['hostname' => 'localhost', 'ifIndex' => '3']),
        ]);
        $this->locator->shouldReceive('locate')->with($this->missing)->andReturn([$this->missing]);

        $this->query->shouldReceive('query')->once()->with(Mockery::on(function (string $flux) {
            $this->assertStringContainsString('(r._measurement == "port" and r["hostname"] == "localhost" and r["id"] == "7") or (r._measurement == "ports" and r["hostname"] == "localhost" and r["ifIndex"] == "3")', $flux);
            $this->assertStringContainsString('range(start: 1970-01-01T00:10:00Z, stop: 1970-01-01T00:35:00Z)', $flux);
            $this->assertStringContainsString('contains(value: r._field, set: ["bytes", "low"])', $flux);
            $this->assertSame(1, substr_count($flux, 'derivative('), 'only counters are converted to rates');
            $this->assertStringContainsString('aggregateWindow(every: 300s, fn: mean, createEmpty: false) |> yield(name: "m0_avg")', $flux);
            $this->assertStringContainsString('yield(name: "m0_min")', $flux);
            $this->assertStringContainsString('yield(name: "m1_max")', $flux);

            return true;
        }))->andReturn([]);

        $this->reader()->read([
            'bits' => new DataSeries($this->metric, 'bytes', 'Traffic', 'bps', 8, minField: 'low'),
            'other' => new DataSeries($this->missing, 'value', 'Other'),
        ], 1000, 1900, 500);
    }

    public function test_values_are_converted_to_the_units_of_the_series(): void
    {
        $this->query->shouldReceive('query')->andReturn($this->tables([
            ['m0_avg', 1200, 'bytes', 2.0], ['m0_min', 1200, 'low', 1.0], ['m0_max', 1200, 'bytes', 2.0],
        ]));

        $data = $this->reader()->read(['bits' => new DataSeries($this->metric, 'bytes', 'Traffic', 'bps', 8, offset: 1, minField: 'low')], 1000, 1100, 500);

        $this->assertSame([17.0], $data->series['bits']['avg']);
        $this->assertSame([9.0], $data->series['bits']['min']);
        $this->assertNull($data->series['bits']['max'], 'left out when it is the average');
    }

    public function test_a_formula_is_calculated_from_the_averages_of_its_fields(): void
    {
        $this->query->shouldReceive('query')->once()->with(Mockery::on(fn (string $flux) => ! str_contains($flux, 'm0_min')))->andReturn($this->tables([
            ['m0_avg', 1200, 'xmt', 10.0], ['m0_avg', 1200, 'rcv', 8.0], ['m0_avg', 1500, 'xmt', 0.0], ['m0_avg', 1500, 'rcv', 0.0],
        ]));

        $data = $this->reader()->read(['loss' => new DataSeries($this->metric, new Formula('xmt', 'rcv', '-', 'xmt', '/', 100, '*'), 'Loss')], 1000, 1400, 500);

        $this->assertSame(['avg' => [20.0, null], 'min' => null, 'max' => null], $data->series['loss'], 'dividing by zero has no value');
    }

    public function test_a_sum_adds_up_the_averages_of_its_series_that_have_data(): void
    {
        $this->query->shouldReceive('query')->andReturn($this->tables([
            ['m0_avg', 1200, 'in', 1.0], ['m0_avg', 1200, 'out', 2.0], ['m0_avg', 1500, 'out', 3.0],
        ]));

        $data = $this->reader()->read([
            'in' => new DataSeries($this->metric, 'in', 'In'),
            'gone' => new DataSeries($this->missing, 'in', 'Gone'),
            'out' => new DataSeries($this->metric, 'out', 'Out'),
            'total' => new SeriesSum(['out', 'gone', 'in'], 'Total'),
            'nothing' => new SeriesSum(['gone'], 'Nothing'),
        ], 1000, 1700, 500);

        $this->assertSame(['avg' => [3.0, 3.0, null], 'min' => null, 'max' => null], $data->series['total']);
        $this->assertArrayNotHasKey('nothing', $data->series, 'a sum without any series with data is left out');
    }

    public function test_long_ranges_are_consolidated_to_whole_poll_intervals(): void
    {
        $this->query->shouldReceive('query')->once()->with(Mockery::on(fn (string $flux) => str_contains($flux, 'every: 900s')))->andReturn([]);

        $data = $this->reader()->read(['in' => new DataSeries($this->metric, 'in', 'In')], 0, 7200, 10);

        $this->assertSame([], $data->series);
        $this->assertSame(900, $data->timestamps[1] - $data->timestamps[0]);
    }

    public function test_values_in_the_query_are_quoted(): void
    {
        $metric = new MetricIdentity('sensor', ['hostname' => 'localhost', 'descr' => 'a "b" ${c} \\d']);
        $this->query->shouldReceive('query')->once()->with(Mockery::on(fn (string $flux) => str_contains($flux, 'r["descr"] == "a \\"b\\" \\${c} \\\\d"')))->andReturn([]);

        $this->reader()->read(['value' => new DataSeries($metric, 'value', 'Value')], 1000, 1900, 500);
    }

    public function test_graphs_read_from_the_configured_data_source(): void
    {
        $default = app(SeriesReader::class);
        LibrenmsConfig::set('graphs.data_source', 'influxdbv2');
        $influx = app(SeriesReader::class);

        $this->assertInstanceOf(RrdSeriesReader::class, $default);
        $this->assertInstanceOf(InfluxSeriesReader::class, $influx);
    }

    private function reader(): InfluxSeriesReader
    {
        return new InfluxSeriesReader($this->query, $this->locator, 'librenms', 300);
    }

    /**
     * @param  list<array{string, int, string, float}>  $rows  result, time, field and value
     * @return list<FluxTable>
     */
    private function tables(array $rows): array
    {
        $table = new FluxTable;
        foreach ($rows as [$result, $time, $field, $value]) {
            $table->records[] = new FluxRecord(0, [
                'result' => $result,
                '_time' => CarbonImmutable::createFromTimestampUTC($time)->toRfc3339String(),
                '_field' => $field,
                '_value' => $value,
            ]);
        }

        return [$table];
    }
}

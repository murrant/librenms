<?php

namespace LibreNMS\Data\Store;

use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyContract;

class RrdFileNameStrategy implements RRDTagStrategyContract
{
    public function __construct(
        public readonly string $baseDir
    ) {
    }

    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    public function getFilePath(string $measurement, array $tags = []): string
    {
        $hostname = Rrd::safeName($tags['hostname']);

        // TODO drop rrd_name tag...
        if (isset($tags['rrd_name'])) {
            $name = $tags['rrd_name'];
            $rrd_name = Rrd::safeName(is_array($name) ? implode('-', $name) : $name);

            return sprintf('%s/%s/%s.rrd', $this->baseDir, $hostname, $rrd_name);
        }

        unset($tags['rrd_name']);
        unset($tags['rrd_def']);

        // special case for wacky ports rrd name
        if ($measurement === 'port') {
            return sprintf('%s/%s/port-id%s.rrd', $this->baseDir, $hostname, $tags['port_id']);
        }

        // special case for poller-perf ALL
        if ($measurement === 'poller-perf' && $tags['module'] === 'ALL') {
            unset($tags['module']);
        }

        $subTags = array_filter($tags, fn ($tag) => ! in_array($tag, $this->filterKeys($measurement)), ARRAY_FILTER_USE_KEY);

        array_unshift($subTags, $measurement);
        $file = Rrd::safeName(implode('-', $subTags));

        return sprintf('%s/%s/%s.rrd', $this->baseDir, $hostname, $file);
    }

    public function findMeasurementsByTags(array $tagConditions): array
    {
        // TODO: Implement findMeasurementsByTags() method.
    }

    public function resolveFilePaths(string $measurement, array $tagConditions): array
    {
        $path = $this->baseDir;
        $hostname = null;

        foreach ($tagConditions as $index => $condition) {
            if ($condition->tag == 'hostname' && $condition->operator == '=') {
                $hostname = $condition->value;
                unset($tagConditions[$index]);
                break;
            }
        }

        // hostname is required
        if ($hostname === null) {
            return [];
        }

        $path .= '/' . $hostname . '/';

        if ($measurement === 'port') {
            $port_id = '*';
            foreach ($tagConditions as $condition) {
                if ($condition->tag === 'port_id') {
                    $port_id = $condition->value;
                    break;
                }
            }

            return glob($path . 'port-id' . $port_id . '.rrd');
        }

        return $path;
    }

    private function filterKeys(string $measurement): array
    {
        return match ($measurement) {
            'sensor' => ['device_id', 'hostname', 'sensor_descr'],
            default => ['device_id', 'hostname'],
        };
    }
}

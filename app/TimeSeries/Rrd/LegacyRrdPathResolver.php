<?php

namespace App\TimeSeries\Rrd;

use App\Facades\DeviceCache;
use App\TimeSeries\Contracts\RrdPathResolver;
use App\TimeSeries\Exceptions\InvalidMetric;
use App\TimeSeries\Metric;
use LibreNMS\RRD\RrdPath;

/**
 * Resolves metrics to the rrd file layout written by the poller: <hostname>/<name>-<label values>.rrd
 */
class LegacyRrdPathResolver implements RrdPathResolver
{
    public function resolve(Metric $metric): RrdPath
    {
        $hostname = DeviceCache::get($metric->deviceId())->hostname;
        if (empty($hostname)) {
            throw new InvalidMetric("Could not resolve hostname for device_id: {$metric->deviceId()}");
        }

        $parts = [$metric->name(), ...array_values($metric->labels())];

        return RrdPath::make((string) $hostname, implode('-', $parts) . '.rrd');
    }
}

<?php

namespace App\TimeSeries\Rrd;

use App\Facades\DeviceCache;
use App\TimeSeries\Contracts\RrdPathResolver;
use App\TimeSeries\Exceptions\InvalidMetric;
use App\TimeSeries\Metric;
use LibreNMS\Data\Store\Rrd;
use LibreNMS\RRD\RrdPath;

/**
 * Resolves metrics to the rrd file layout written by the poller: <hostname>/<name>-<label values>.rrd
 *
 * This is the only place metric file paths are built. Parts are in a fixed order (name, then label
 * values as declared) and escaped by RrdPath (Rrd::safeName), so no part contains a path separator.
 * Escaping and the - separator are lossy, distinct values can collide; that is the existing storage format.
 */
class LegacyRrdPathResolver implements RrdPathResolver
{
    public function resolve(Metric $metric): RrdPath
    {
        if ($metric->name() === '') {
            throw new InvalidMetric('Metric name cannot be empty');
        }

        foreach ($metric->labels() as $label => $value) {
            if ((string) $value === '') {
                throw new InvalidMetric("Metric {$metric->name()} label $label cannot be empty");
            }
        }

        $hostname = (string) DeviceCache::get($metric->deviceId())->hostname;
        // the directory component must stay inside the rrd directory once escaped
        if (in_array(Rrd::safeName(trim($hostname, '[]')), ['', '.', '..'], true)) {
            throw new InvalidMetric("Invalid hostname for device_id {$metric->deviceId()}: '$hostname'");
        }

        $parts = [$metric->name(), ...array_values($metric->labels())];

        return RrdPath::make($hostname, implode('-', $parts) . '.rrd');
    }
}

<?php

namespace App\TimeSeries\Rrd;

use App\Facades\DeviceCache;
use App\TimeSeries\Contracts\RrdPathResolver;
use App\TimeSeries\Exceptions\InvalidMetric;
use App\TimeSeries\MetricIdentity;
use App\TimeSeries\MetricSchema;
use LibreNMS\RRD\RrdPath;

/**
 * Resolves metrics to the rrd file layout written by the poller: <hostname>/<name>-<label values>.rrd
 * Label values are ordered by the MetricSchema.
 */
class LegacyRrdPathResolver implements RrdPathResolver
{
    public function __construct(private readonly MetricSchema $schema)
    {
    }

    public function resolve(MetricIdentity $identity): RrdPath
    {
        $deviceId = $identity->labels[MetricSchema::DEVICE_LABEL] ?? null;
        if ($deviceId === null) {
            throw new InvalidMetric("Metric $identity->name must have a " . MetricSchema::DEVICE_LABEL . ' label.');
        }

        $hostname = DeviceCache::get($deviceId)->hostname;
        if (empty($hostname)) {
            throw new InvalidMetric("Could not resolve hostname for device_id: $deviceId");
        }

        $parts = [$identity->name, ...$this->schema->orderedValues($identity)];

        return RrdPath::make((string) $hostname, implode('-', $parts) . '.rrd');
    }
}

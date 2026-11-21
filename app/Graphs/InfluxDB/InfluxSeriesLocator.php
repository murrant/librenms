<?php

namespace App\Graphs\InfluxDB;

use App\Data\TimeSeries\MetricIdentity;
use App\Models\Port;
use LibreNMS\Data\Store\MetricNames;

/**
 * Finds the measurement and tags InfluxDB keeps a metric under
 */
class InfluxSeriesLocator
{
    /** the value the datastores store for an empty tag */
    private const string BLANK = '_blank_';

    public function __construct(private readonly MetricNames $metricNames)
    {
    }

    /**
     * The metric as identified and under the names it had before, so data that is still stored under them is found.
     * Each is only the tags the stored series must have, the datastores store more (descriptions) that are not matched.
     *
     * @return list<MetricIdentity>
     */
    public function locate(MetricIdentity $metric): array
    {
        $names = [$this->blank($metric)];

        $legacy = $this->metricNames->legacyOf($metric);
        if ($legacy !== null) {
            $tags = $legacy->tagsOf($metric);

            // ports were stored by their ifIndex, which the identity of a port does not have
            if ($metric->measurement === 'port' && isset($metric->tags['id'])) {
                $ifIndex = Port::query()->whereKey($metric->tags['id'])->value('ifIndex');
                if ($ifIndex === null) {
                    return $names;
                }
                $tags['ifIndex'] = $ifIndex;
            }

            // the datastores add the hostname to metrics written without an identity
            if (isset($metric->tags['hostname'])) {
                $tags = ['hostname' => $metric->tags['hostname'], ...$tags];
            }

            $names[] = $this->blank(new MetricIdentity($legacy->measurement, $tags));
        }

        return $names;
    }

    /**
     * The datastores store empty() tags as blank, which includes 0
     */
    private function blank(MetricIdentity $metric): MetricIdentity
    {
        return new MetricIdentity($metric->measurement, array_map(fn ($value) => empty($value) ? self::BLANK : (string) $value, $metric->tags));
    }
}

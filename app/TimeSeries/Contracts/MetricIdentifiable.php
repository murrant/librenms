<?php

namespace App\TimeSeries\Contracts;

use App\TimeSeries\MetricIdentity;

interface MetricIdentifiable
{
    /**
     * Get the metric identity for this model.
     *
     * @param string $metricName
     * @return MetricIdentity
     */
    public function toMetricIdentity(string $metricName): MetricIdentity;
}

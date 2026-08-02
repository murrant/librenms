<?php

namespace LibreNMS\Data\Graphing;

use App\TimeSeries\MetricIdentity;

readonly class DataSeries
{
    public function __construct(
        public MetricIdentity $metric,
        public string $field,
        public string $description,
) {}
}

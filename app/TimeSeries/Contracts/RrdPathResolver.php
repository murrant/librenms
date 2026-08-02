<?php

namespace App\TimeSeries\Contracts;

use App\TimeSeries\MetricIdentity;

interface RrdPathResolver
{
    public function resolve(MetricIdentity $identity): string;
}

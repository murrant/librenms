<?php

namespace App\TimeSeries\Contracts;

use App\TimeSeries\MetricIdentity;
use LibreNMS\RRD\RrdPath;

interface RrdPathResolver
{
    /**
     * Where the rrd file for the given metric is stored
     */
    public function resolve(MetricIdentity $identity): RrdPath;
}

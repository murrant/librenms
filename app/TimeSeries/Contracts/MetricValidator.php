<?php


namespace App\TimeSeries\Contracts;

use App\TimeSeries\MetricIdentity;

interface MetricValidator
{
    public function validate(?MetricIdentity $metric = null, array $extra = []);
}

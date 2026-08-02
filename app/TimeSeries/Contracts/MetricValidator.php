<?php


namespace App\TimeSeries\Contracts;

use App\TimeSeries\MetricIdentity;

interface MetricValidator
{
    public function validate(?MetricIdentity $metric = null, array $extra = []);

    public function hasValidFiles(): bool;

    public function hasAttempted(): bool;
}

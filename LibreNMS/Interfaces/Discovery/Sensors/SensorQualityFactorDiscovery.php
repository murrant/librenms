<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorQualityFactorDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverQualityFactorSensors(): Collection;
}

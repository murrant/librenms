<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorPowerFactorDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverPowerFactorSensors(): Collection;
}

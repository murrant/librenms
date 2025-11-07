<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorCoolingDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverCoolingSensors(): Collection;
}

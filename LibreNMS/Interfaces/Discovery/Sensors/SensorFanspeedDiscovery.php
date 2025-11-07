<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorFanspeedDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverFanspeedSensors(): Collection;
}

<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorPowerDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverPowerSensors(): Collection;
}

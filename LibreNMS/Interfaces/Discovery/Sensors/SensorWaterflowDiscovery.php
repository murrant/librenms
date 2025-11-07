<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorWaterflowDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverWaterflowSensors(): Collection;
}

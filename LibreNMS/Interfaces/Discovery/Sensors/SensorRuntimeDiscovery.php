<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorRuntimeDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverRuntimeSensors(): Collection;
}

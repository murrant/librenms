<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorCountDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverCountSensors(): Collection;
}

<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorLoadDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverLoadSensors(): Collection;
}

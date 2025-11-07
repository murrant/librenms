<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorStateDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverStateSensors(): Collection;
}

<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorDelayDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverDelaySensors(): Collection;
}

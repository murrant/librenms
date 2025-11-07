<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorCurrentDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverCurrentSensors(): Collection;
}

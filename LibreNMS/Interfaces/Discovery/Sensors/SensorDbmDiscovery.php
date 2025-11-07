<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorDbmDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverDbmSensors(): Collection;
}

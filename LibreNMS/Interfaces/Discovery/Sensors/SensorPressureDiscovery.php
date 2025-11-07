<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorPressureDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverPressureSensors(): Collection;
}

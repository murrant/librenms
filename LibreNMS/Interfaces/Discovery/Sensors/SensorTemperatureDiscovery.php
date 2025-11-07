<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorTemperatureDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverTemperatureSensors(): Collection;
}

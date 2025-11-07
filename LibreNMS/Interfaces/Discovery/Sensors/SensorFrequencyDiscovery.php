<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorFrequencyDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverFrequencySensors(): Collection;
}

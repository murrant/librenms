<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorChromaticDispersionDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverChromaticDispersionSensors(): Collection;
}

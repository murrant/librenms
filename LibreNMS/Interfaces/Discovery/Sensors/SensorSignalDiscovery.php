<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorSignalDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverSignalSensors(): Collection;
}

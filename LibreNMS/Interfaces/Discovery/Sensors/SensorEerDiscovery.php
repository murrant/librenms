<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorEerDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverEerSensors(): Collection;
}

<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorSnrDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverSnrSensors(): Collection;
}

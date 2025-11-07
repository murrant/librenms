<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorPowerConsumedDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverPowerConsumedSensors(): Collection;
}

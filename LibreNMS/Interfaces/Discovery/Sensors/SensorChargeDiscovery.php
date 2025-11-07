<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorChargeDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverChargeSensors(): Collection;
}

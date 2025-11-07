<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorLossDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverLossSensors(): Collection;
}

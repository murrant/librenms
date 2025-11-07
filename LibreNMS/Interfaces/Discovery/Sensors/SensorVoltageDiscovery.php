<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorVoltageDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverVoltageSensors(): Collection;
}

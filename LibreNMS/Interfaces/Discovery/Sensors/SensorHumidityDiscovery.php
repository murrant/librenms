<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorHumidityDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverHumiditySensors(): Collection;
}

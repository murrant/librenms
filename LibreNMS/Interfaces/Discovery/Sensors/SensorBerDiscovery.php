<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorBerDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverBerSensors(): Collection;
}

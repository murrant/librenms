<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorBitrateDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverBitrateSensors(): Collection;
}

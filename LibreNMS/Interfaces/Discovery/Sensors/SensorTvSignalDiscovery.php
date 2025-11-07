<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorTvSignalDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverTvSignalSensors(): Collection;
}

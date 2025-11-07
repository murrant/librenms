<?php

namespace LibreNMS\Interfaces\Discovery\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorAirflowDiscovery
{
    /**
     * @return Collection<Sensor>
     */
    public function discoverAirflowSensors(): Collection;
}

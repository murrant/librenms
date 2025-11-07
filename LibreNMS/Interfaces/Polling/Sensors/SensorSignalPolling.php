<?php

namespace LibreNMS\Interfaces\Polling\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorSignalPolling
{
    /**
     * @param  Collection<Sensor>  $sensors
     * @return Collection<Sensor>
     */
    public function pollSignalSensors(Collection $sensors): Collection;
}

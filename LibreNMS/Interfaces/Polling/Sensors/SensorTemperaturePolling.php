<?php

namespace LibreNMS\Interfaces\Polling\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorTemperaturePolling
{
    /**
     * @param  Collection<Sensor>  $sensors
     * @return Collection<Sensor>
     */
    public function pollTemperatureSensors(Collection $sensors): Collection;
}

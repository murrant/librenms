<?php

namespace LibreNMS\Interfaces\Polling\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorBerPolling
{
    /**
     * @param  Collection<Sensor>  $sensors
     * @return Collection<Sensor>
     */
    public function pollBerSensors(Collection $sensors): Collection;
}

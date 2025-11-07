<?php

namespace LibreNMS\Interfaces\Polling\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorQualityFactorPolling
{
    /**
     * @param  Collection<Sensor>  $sensors
     * @return Collection<Sensor>
     */
    public function pollQualityFactorSensors(Collection $sensors): Collection;
}

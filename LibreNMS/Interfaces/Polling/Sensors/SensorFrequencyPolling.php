<?php

namespace LibreNMS\Interfaces\Polling\Sensors;

use App\Models\Sensor;
use Illuminate\Support\Collection;

interface SensorFrequencyPolling
{
    /**
     * @param  Collection<Sensor>  $sensors
     * @return Collection<Sensor>
     */
    public function pollFrequencySensors(Collection $sensors): Collection;
}

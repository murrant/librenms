<?php

namespace App\View\Components;

use App\Models\Sensor;
use App\Models\Transceiver;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use LibreNMS\Util\Number;

class TransceiverSensors extends Component
{
    public Collection $groupedSensors;

    /**
     * Create a new component instance.
     */
    public function __construct(
        public Transceiver $transceiver,
    ) {
        $this->groupedSensors = Sensor::where('device_id', $this->transceiver->device_id)
            ->whereNotNull('entPhysicalIndex')
            ->where('entPhysicalIndex', $this->transceiver->entity_physical_index)
            ->where('sensor_type', 'transceiver')
            ->get()
            ->groupBy('sensor_class');
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.transceiver-sensors');
    }

    public function sensorValue(Sensor $sensor): string
    {
        return Number::formatSi($sensor->sensor_current, 3, 3, __('sensors.' . $sensor->sensor_class . '.unit'));
    }
}

<?php

namespace App\Http\Livewire;

use App\Facades\DeviceCache;
use Livewire\Component;

class DeviceOverviewWidget extends Component
{
    /**
     * @var \App\Models\Device
     */
    public $device;

    public function __construct($id = null)
    {
        parent::__construct($id);
        $this->device = DeviceCache::getPrimary();
    }

    public function render()
    {
        return view('livewire.device-overview-widget')->extends('components.dashboard-widget');
    }
}

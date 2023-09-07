<?php

namespace App\View\Components;

use App\Models\Device;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use LibreNMS\Config;

class DeviceSettingsBar extends Component
{
    public array $tabs = [
        'device',
        'snmp',
        'ports',
        'routing',
        'apps',
        'alert-rules',
        'modules',
        'services',
        'ipmi',
        'health',
        'wireless-sensors',
        'storage',
        'processors',
        'mempools',
        'misc',
        'component',
        'customoid',
    ];

    /**
     * Create a new component instance.
     */
    public function __construct(
        public Device $device,
        public ?string $selected = null
    ) {
        if (!Config::get('show_services')) {
            unset($this->tabs['services']);
        }

        // hide tabs from ping only devices
        if ($device->snmp_disable) {
            unset(
                $this->tabs['ports'],
                $this->tabs['apps'],
                $this->tabs['modules'],
                $this->tabs['storage'],
                $this->tabs['health'],
                $this->tabs['processors'],
                $this->tabs['mempools'],
                $this->tabs['customoid'],
                $this->tabs['customoid'],
                $this->tabs['wireless-sensors'],
            );

            return; // skip sql queries below
        }

        if (! $device->sensors()->exists()) {
            unset($this->tabs['health']);
        }

        if (! $device->processors()->exists()) {
            unset($this->tabs['processors']);
        }

        if (! $device->mempools()->exists()) {
            unset($this->tabs['mempools']);
        }

        if (! $device->storage()->exists()) {
            unset($this->tabs['storage']);
        }

        if (! $device->wirelessSensors()->exists()) {
            unset($this->tabs['wireless-sensors']);
        }
    }

    public function options(): array
    {
        $options = [];

        foreach ($this->tabs as $tab) {
            $options[$tab] = [
                'text' => trans('device.settings.tabs.' . $tab),
                'link' => url('device/' . $this->device->device_id . '/edit/' . $tab),
            ];
        }

        return $options;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.device-settings-bar');
    }
}

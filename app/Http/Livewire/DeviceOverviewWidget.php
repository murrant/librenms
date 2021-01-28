<?php

namespace App\Http\Livewire;

use App\Facades\DeviceCache;
use LibreNMS\Config;
use LibreNMS\Exceptions\InvalidIpException;
use LibreNMS\Util\IP;
use LibreNMS\Util\Rewrite;
use LibreNMS\Util\Time;
use Livewire\Component;

class DeviceOverviewWidget extends Component
{
    /**
     * @var \App\Models\Device
     */
    public $device;
    public $ip;
    public $ip_type = 'ip';
    public $hardware;
    public $os;
    public $contact;
    public $date_added;
    public $last_discovered;
    public $uptime;
    public $uptime_descr;

    public function __construct($id = null)
    {
        parent::__construct($id);
        $this->updateData();
    }

    public function render()
    {
        return view('livewire.device-overview-widget');
    }

    protected function updateData(): void
    {
        $this->device = DeviceCache::getPrimary();

        if (! empty($this->device->overwrite_ip)) {
            $this->ip = $this->device->overwrite_ip;
            $this->ip_type = 'assigned';
        } elseif (! empty($this->device->ip)) {
            $this->ip = $this->device->ip;
            $this->ip_type = 'resolved';
        } elseif (Config::get('force_ip_to_sysname') === true) {
            try {
                $this->ip = IP::parse($this->device->hostname);
            } catch (InvalidIpException $e) {
                // don't add an ip line
            }
        }

        $this->hardware = Rewrite::ciscoHardware($this->device);

        $this->os = Config::getOsSetting($this->device->os, 'text') . ' ' . $this->device->version;
        if ($this->device->features) {
            $this->os .= " ({$this->device->features})";
        }

        $this->contact = $this->device->getAttrib('override_sysContact_bool') ? $this->device->getAttrib('override_sysContact_string') : $this->device->sysContact;
        $this->date_added = $this->device->inserted ? trans('device.time_ago', ['time' => Time::formatInterval(time() - strtotime($this->device->inserted), 'long', ['seconds'])]) : null;
        $this->last_discovered = $this->device->last_discovered ? trans('device.time_ago', ['time' => Time::formatInterval(time() - strtotime($this->device->last_discovered), 'long', ['seconds'])]) : trans('device.last_discovered.never');

        if ($this->device->status) {
            $this->uptime = Time::formatInterval($this->device->uptime);
            $this->uptime_descr = trans('device.status.uptime');
        } else {
            $this->uptime = Time::formatInterval(time() - strtotime($this->device->last_polled));
            $this->uptime_descr = trans('device.status.downtime');
        }
    }
}

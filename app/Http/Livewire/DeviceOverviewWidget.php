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
    public $location;

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
        $device = DeviceCache::getPrimary();
        $this->device = $device;

        if (! empty($device->overwrite_ip)) {
            $this->ip = $device->overwrite_ip;
            $this->ip_type = 'assigned';
        } elseif (! empty($device->ip)) {
            $this->ip = $device->ip;
            $this->ip_type = 'resolved';
        } elseif (Config::get('force_ip_to_sysname') === true) {
            try {
                $this->ip = IP::parse($device->hostname);
            } catch (InvalidIpException $e) {
                // don't add an ip line
            }
        }

        $this->hardware = Rewrite::ciscoHardware($device);

        $this->os = Config::getOsSetting($device->os, 'text') . ' ' . $device->version;
        if ($device->features) {
            $this->os .= " ({$device->features})";
        }

        $this->contact = $device->getAttrib('override_sysContact_bool') ? $device->getAttrib('override_sysContact_string') : $device->sysContact;
        $this->date_added = $device->inserted ? trans('device.time_ago', ['time' => Time::formatInterval(time() - strtotime($device->inserted), 'long', ['seconds'])]) : null;
        $this->last_discovered = $device->last_discovered ? trans('device.time_ago', ['time' => Time::formatInterval(time() - strtotime($device->last_discovered), 'long', ['seconds'])]) : trans('device.last_discovered.never');

        if ($device->status) {
            $this->uptime = Time::formatInterval($device->uptime);
            $this->uptime_descr = trans('device.status.uptime');
        } else {
            $this->uptime = Time::formatInterval(time() - strtotime($device->last_polled));
            $this->uptime_descr = trans('device.status.downtime');
        }

        if ($device->location) {
            $this->location = $device->location->display();

        }
    }
}

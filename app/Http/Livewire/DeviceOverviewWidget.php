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

    public function __construct($id = null)
    {
        parent::__construct($id);
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

        $this->date_added = $this->device->inserted ? (Time::formatInterval(time() - strtotime($this->device->inserted)) . ' ' . __('ago')) : null;
    }

    public function render()
    {
        return view('livewire.device-overview-widget');
    }
}

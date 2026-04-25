<?php

namespace App\Data\Polling\Methods;

use App\Data\Polling\ProbeResult;
use App\Data\Secrets\IpmiSecret;
use App\Models\Device;
use LibreNMS\Interfaces\PollingMethod;

class Ipmi implements PollingMethod
{
    public function probe(Device $device): ProbeResult
    {
        return ProbeResult::failure('Not implemented'); // TODO
    }

    public function getDeviceSettings(): array
    {
        return [
            [
                'name' => 'hostname',
                'type' => 'text',
                'default' => '',
                'required' => true,
                'description' => 'IPMI/BMC hostname',
                'storage' => 'attrib',
                'key' => 'ipmi_hostname',
            ],
            [
                'name' => 'port',
                'type' => 'number',
                'default' => 623,
                'required' => true,
                'description' => 'IPMI/BMC port',
                'storage' => 'attrib',
                'key' => 'ipmi_port',
            ],
            [
                'name' => 'ciphersuite',
                'type' => 'text',
                'default' => '',
                'required' => true,
                'description' => 'IPMI/BMC ciphersuite',
                'storage' => 'attrib',
                'key' => 'ipmi_ciphersuite',
            ],
            [
                'name' => 'timeout',
                'type' => 'number',
                'default' => 3,
                'required' => true,
                'description' => 'IPMI/BMC timeout',
                'storage' => 'attrib',
                'key' => 'ipmi_timeout',
            ],
        ];
    }

    public function isEnabled(Device $device): bool
    {
        return true;
    }

    public function isConfigured(Device $device): bool
    {
        return collect($this->getDeviceSettings())
            ->contains(fn (array $setting): bool => (string) $device->getAttrib($setting['key']) !== '');
    }

    public function lastCheckSuccessful(Device $device): ?bool
    {
        return null;
    }

    public function getSecret(Device $device): IpmiSecret
    {
        $data = $device->secrets->firstWhere('secret_type', 'ipmi')->data ?? [];

        return IpmiSecret::fromArray($data);
    }
}

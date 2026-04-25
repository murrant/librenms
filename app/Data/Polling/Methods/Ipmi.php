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

    public function getSettingsSchema(): array
    {
        return [
            'hostname' => [
                'type' => 'text',
            ],
            'port' => [
                'type' => 'number',
            ],
            'ciphersuite' => [
                'type' => 'text',
            ],
            'timeout' => [
                'type' => 'number',
            ],
        ];
    }

    public function getSecret(Device $device): IpmiSecret
    {
        $data = $device->secrets->firstWhere('secret_type', 'ipmi')->data ?? [];

        return IpmiSecret::fromArray($data);
    }

    public function getDefaults(): array
    {
        return [
            'affects_availability' => false,
            'hostname' => '',
            'port' => 623,
            'ciphersuite' => '',
            'timeout' => 3,
        ];
    }

    public function getRules(): array
    {
        return [
            'hostname' => ['required', 'string'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'ciphersuite' => ['nullable', 'string'],
            'timeout' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Data\Polling\Methods;

use App\Data\Polling\ProbeResult;
use App\Data\Secrets\SnmpSecret;
use App\Models\Device;
use LibreNMS\Interfaces\PollingMethod;
use SnmpQuery;

class Snmp implements PollingMethod
{
    public function probe(Device $device): ProbeResult
    {
        $start_time = microtime(true);
        $response = SnmpQuery::device($device)->get('SNMPv2-MIB::sysObjectID.0');

        if ($response->getExitCode() !== 0 && ! $response->isValid()) {
            return ProbeResult::failure('SNMP query failed');
        }

        return ProbeResult::success((microtime(true) - $start_time) * 1000);
    }

    public function isEnabled(Device $device): bool
    {
        return ! $device->snmp_disable;
    }

    public function lastCheckSuccessful(Device $device): ?bool
    {
        return null;
    }

    public function getSecret(Device $device): SnmpSecret
    {
        $data = $device->secrets->firstWhere('secret_type', 'snmp')->data ?? [];

        return SnmpSecret::fromArray($data);
    }

    public function getSettingsSchema(): array
    {
        return [
            'transport' => [
                'type' => 'select',
                'options' => [
                    'udp' => 'UDP',
                    'tcp' => 'TCP',
                    'udp6' => 'UDP6',
                    'tcp6' => 'TCP6',
                ],
            ],
            'port' => [
                'type' => 'number',
            ],
            'timeout' => [
                'type' => 'number',
            ],
            'retries' => [
                'type' => 'number',
            ],
            'max_repeaters' => [
                'type' => 'number',
            ],
            'max_oid' => [
                'type' => 'number',
            ],
        ];
    }

    public function getDefaults(): array
    {
        return [
            'affects_availability' => true,
            'transport' => 'default',
            'port' => 161,
            'timeout' => 3,
            'retries' => 1,
            'max_repeaters' => 0,
            'max_oid' => 10,
        ];
    }

    public function getRules(): array
    {
        return [
            'transport' => ['required', 'string', 'in:udp,tcp,udp6,tcp6'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:60'],
            'retries' => ['nullable', 'integer', 'min:0', 'max:10'],
            'max_repeaters' => ['nullable', 'integer', 'min:0', 'max:30'],
            'max_oid' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

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

    public function getDeviceSettings(): array
    {
        return [
            [
                'name' => 'transport',
                'type' => 'select',
                'default' => 'udp',
                'options' => [
                    'udp' => 'UDP',
                    'tcp' => 'TCP',
                    'udp6' => 'UDP6',
                    'tcp6' => 'TCP6',
                ],
                'required' => true,
                'rules' => ['nullable', 'string', 'in:udp,tcp,udp6,tcp6'],
                'description' => 'SNMP transport',
                'storage' => 'field',
                'key' => 'transport',
            ],
            [
                'name' => 'port',
                'type' => 'number',
                'default' => 161,
                'required' => true,
                'rules' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'description' => 'SNMP port number',
                'storage' => 'field',
                'key' => 'port',
            ],
            [
                'name' => 'timeout',
                'type' => 'number',
                'default' => 3,
                'required' => true,
                'rules' => ['nullable', 'integer', 'min:1', 'max:60'],
                'description' => 'SNMP timeout',
                'storage' => 'field',
                'key' => 'timeout',
            ],
            [
                'name' => 'retries',
                'type' => 'number',
                'default' => 1,
                'required' => true,
                'rules' => ['nullable', 'integer', 'min:0', 'max:10'],
                'description' => 'SNMP retries',
                'storage' => 'field',
                'key' => 'retries',
            ],
            [
                'name' => 'max_repeaters',
                'type' => 'number',
                'default' => 0,
                'required' => true,
                'rules' => ['nullable', 'integer', 'min:0', 'max:10'],
                'description' => 'SNMP repeaters',
                'storage' => 'attrib',
                'key' => 'snmp_max_repeaters',
            ],
            [
                'name' => 'max_oid',
                'type' => 'number',
                'default' => 10,
                'required' => true,
                'rules' => ['nullable', 'integer', 'min:1', 'max:100'],
                'description' => 'SNMP max OIDs',
                'storage' => 'attrib',
                'key' => 'snmp_max_oid',
            ],
        ];
    }

    public function isEnabled(Device $device): bool
    {
        return ! $device->snmp_disable;
    }

    public function isConfigured(Device $device): bool
    {
        return $this->getSecret($device) !== null;
    }

    public function lastCheckSuccessful(Device $device): ?bool
    {
        if (! $this->isConfigured($device)) {
            return null;
        }

        return $device->last_polled !== null && ! $device->snmp_disable; // TODO actual check
    }

    public function getSecret(Device $device): SnmpSecret
    {
        $data = $device->secrets->firstWhere('secret_type', 'snmp')->data ?? [];

        return SnmpSecret::fromArray($data);
    }
}

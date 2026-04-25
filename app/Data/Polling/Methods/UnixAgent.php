<?php

namespace App\Data\Polling\Methods;

use App\Data\Polling\ProbeResult;
use App\Facades\LibrenmsConfig;
use App\Models\Device;
use ErrorException;
use LibreNMS\Interfaces\PollingMethod;
use LibreNMS\Util\Rewrite;

class UnixAgent implements PollingMethod
{
    public function probe(Device $device): ProbeResult
    {
        try {
            $port = $device->getAttrib('override_Unixagent_port') ?: LibrenmsConfig::get('unix-agent.port');
            $timeout = LibrenmsConfig::get('unix-agent.connection-timeout');

            $poller_target = Rewrite::addIpv6Brackets($device->pollerTarget());
            $start_time = microtime(true);
            @fsockopen($poller_target, $port, $errno, $errstr, $timeout);

            if ($errstr) {
                return ProbeResult::failure($errstr);
            }

            return ProbeResult::success((microtime(true) - $start_time) * 1000);
        } catch (ErrorException $e) {
            return ProbeResult::failure($e->getMessage());
        }
    }

    public function getSettingsSchema(): array
    {
        return [
            'port' => [
                'type' => 'number',
                'default' => 6556,
                'min' => 1,
                'max' => 65535,
            ],
        ];
    }

    public function getDefaults(): array
    {
        return [
            'port' => 6556,
        ];
    }

    public function getRules(): array
    {
        return [
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }

    public function isEnabled(Device $device): bool
    {
        return true;
    }

    public function lastCheckSuccessful(Device $device): ?bool
    {
        return null;
    }

    public function getSecret(Device $device): null
    {
        return null;
    }
}

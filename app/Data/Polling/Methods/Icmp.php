<?php

namespace App\Data\Polling\Methods;

use App\Data\Polling\ProbeResult;
use App\Models\Device;
use App\Models\Eventlog;
use LibreNMS\Data\Source\Fping;
use LibreNMS\Data\Source\FpingResponse;
use LibreNMS\Enum\Severity;
use LibreNMS\Interfaces\PollingMethod;

class Icmp implements PollingMethod
{
    public function probe(Device $device): ProbeResult
    {
        $status = (new Fping)->ping($device->pollerTarget(), $device->ipFamily());

        if ($status->duplicates > 0) {
            Eventlog::log('Duplicate ICMP response detected! This could indicate a network issue.', $device, 'icmp', Severity::Warning);
            $status->ignoreFailure(); // when duplicate is detected fping returns 1. The device is up, but there is another issue. Clue admins in with above event.
        }

        if ($status->success()) {
            return ProbeResult::success($status->avg_latency);
        }

        return match ($status->exit_code) {
            FpingResponse::UNREACHABLE => ProbeResult::failure('Device unreachable'),
            FpingResponse::INVALID_HOST => ProbeResult::failure('Invalid hostname/IP'),
            FpingResponse::INVALID_ARGS => ProbeResult::failure('Invalid arguments'),
            FpingResponse::SYS_CALL_FAIL => ProbeResult::failure('System call failed'),
            default => ProbeResult::failure('Unknown error'),
        };
    }

    public function getDeviceSettings(): array
    {
        return [];
    }

    public function isEnabled(Device $device): bool
    {
        return $device->status_reason !== 'icmp';
    }

    public function isConfigured(Device $device): bool
    {
        return true;
    }

    public function lastCheckSuccessful(Device $device): ?bool
    {
        return $device->last_ping_timetaken !== null; // TODO data not available
    }

    public function getSecret(Device $device): null
    {
        return null;
    }
}

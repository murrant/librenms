<?php

namespace App\Data\Polling\Methods;

use App\Data\Polling\ProbeResult;
use App\Models\Device;
use App\Models\Eventlog;
use LibreNMS\Data\Source\Icmp\Fping;
use LibreNMS\Data\Source\Icmp\FpingResponse;
use LibreNMS\Enum\FpingExitCode;
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

        if ($status->isAlive()) {
            return ProbeResult::success($status->avg_latency);
        }

        return match($status->exit_code) {
            FpingExitCode::Unreachable => ProbeResult::failure('Device unreachable'),
            FpingExitCode::InvalidHost => ProbeResult::failure('Invalid hostname/IP'),
            FpingExitCode::InvalidArgs => ProbeResult::failure('Invalid arguments'),
            FpingExitCode::SysCallFail => ProbeResult::failure('System call failed'),
            default => ProbeResult::failure('Unknown error'),
        };
    }

    public function getSettingsSchema(): array
    {
        return [];
    }

    public function getDefaults(): array
    {
        return [
            'affects_availability' => true,
        ];
    }

    public function getRules(): array
    {
        return [];
    }
}

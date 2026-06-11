<?php

namespace App\Actions\Device;

use App\Models\Device;
use LibreNMS\Enum\AvailabilitySource;
use LibreNMS\Enum\PollingMethodType;

class CheckDeviceAvailability
{
    public function __construct(
        private readonly SetDeviceAvailability $setDeviceAvailability,
        private readonly DeviceIcmpIsAvailable $deviceIcmpIsAvailable,
        private readonly DeviceSnmpIsAvailable $deviceSnmpIsAvailable,
        private readonly DeviceMtuTest $deviceMtuTest,
    ) {
    }

    public function execute(Device $device, bool $commit = false): bool
    {
        $icmpMethod = $device->getPollingMethod(PollingMethodType::Icmp);
        if ($icmpMethod?->enabled) {
            $ping_response = $this->deviceIcmpIsAvailable->execute($device);
            $icmpMethod->last_check_successful = $ping_response->isAlive();
            $icmpMethod->last_checked_at = now();

            if ($commit) {
                $ping_response->saveStats($device);
            }

            if ($icmpMethod->last_check_successful) {
                $device->mtu_status = $this->deviceMtuTest->execute($device);
            }
        }

        $snmpMethod = $device->getPollingMethod(PollingMethodType::Snmp);
        if ($snmpMethod?->enabled) {
            $icmp_success = ! $icmpMethod?->enabled || $icmpMethod->last_check_successful || ! $icmpMethod->affects_availability;
            if ($icmp_success) {
                $snmpMethod->last_check_successful = $this->deviceSnmpIsAvailable->execute($device);
                $snmpMethod->last_checked_at = now();
            } else {
                $snmpMethod->last_check_successful = false;
            }
        }

        if ($icmpMethod?->enabled && $icmpMethod?->affects_availability && ! $icmpMethod->last_check_successful) {
            $this->setDeviceAvailability->execute($device, false, AvailabilitySource::Icmp, $commit);
        } elseif ($snmpMethod?->enabled && $snmpMethod?->affects_availability) {
            $this->setDeviceAvailability->execute($device, $snmpMethod->last_check_successful, AvailabilitySource::Snmp, $commit);
        } elseif ($icmpMethod?->enabled && $icmpMethod?->affects_availability) {
            $this->setDeviceAvailability->execute($device, true, AvailabilitySource::Icmp, $commit);
        } else {
            $this->setDeviceAvailability->execute($device, true, AvailabilitySource::None, $commit);
        }

        if ($commit) {
            $icmpMethod?->save();
            $snmpMethod?->save();
            $device->save(); // confirm device is saved
        }

        return $device->status;
    }
}

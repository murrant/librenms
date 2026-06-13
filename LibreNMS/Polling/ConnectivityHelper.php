<?php

/*
 * ConnectivityHelper.php
 *
 * Helper to check polling method availability and module gating for a device.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2021 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Polling;

use App\Models\Device;
use LibreNMS\Enum\PollingMethodType;

class ConnectivityHelper
{
    public function __construct(
        private readonly Device $device,
    ) {
    }

    /**
     * Check if a specific polling method is configured and enabled for this device.
     */
    public function enabled(PollingMethodType $type): bool
    {
        return (bool) $this->device->getPollingMethod($type)?->enabled;
    }

    public function isAvailable(): bool
    {
        foreach ($this->device->pollingMethods as $method) {
            if ($method->enabled && $method->affects_availability && ! $method->last_check_successful) {
                return true;
            }
        }

        return false;
    }

    public function hasAvailability(): bool
    {
        foreach ($this->device->pollingMethods as $method) {
            if ($method->enabled && $method->affects_availability) {
                return true;
            }
        }

        return false;
    }

    public function can(PollingMethodType $type): bool
    {
        $method = $this->device->getPollingMethod($type);

        return $method?->enabled && $method?->last_check_successful;
    }

    public function canSnmp(): bool
    {
        return $this->can(PollingMethodType::Snmp);
    }

    public function canIpmi(): bool
    {
        return $this->can(PollingMethodType::Ipmi);
    }

    public function canIcmp(): bool
    {
        return $this->can(PollingMethodType::Icmp);
    }

    public function canUnixAgent(): bool
    {
        return $this->can(PollingMethodType::UnixAgent);
    }
}

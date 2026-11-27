<?php

/*
 * InfineraGroove.php
 *
 * -Description-
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       https://www.librenms.org
 * @copyright  2020 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\OS;

use App\Models\Device;

class InfineraGroove extends \LibreNMS\OS
{
    public function discoverOS(Device $device): void
    {
        $oid_list = [
            'neType.0',
            'softwareloadSwloadState.1',
            'softwareloadSwloadState.2',
            'softwareloadSwloadVersion.1',
            'softwareloadSwloadVersion.2',
            'inventoryManufacturerNumber.shelf.1.0.0.0',
        ];

        $data = snmp_get_multi($this->getDeviceArray(), $oid_list, '-OUQs', 'CORIANT-GROOVE-MIB');

        foreach ($data as $value) {
            if (isset($value['softwareloadSwloadState']) && $value['softwareloadSwloadState'] == 'active') {
                $device->version = $value['softwareloadSwloadVersion'];
                break;
            }
        }
        $device->hardware = $data[0]['neType'] ?? null;
        $device->serial = $data['shelf.1.0.0.0']['inventoryManufacturerNumber'] ?? null;
    }

    /**
     * @author     Nick Hilliard <nick@foobar.org>
     * @copyright  2019 Nick Hilliard
     */
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = parent::collectPortRows($knownPorts, $selected);
        foreach (['eth100g' => 100, 'eth40g' => 40, 'eth10g' => 10, 'fc16g' => 16, 'fc8g' => 8] as $type => $speed) {
            $columns = ['ifAlias' => 'AliasName', 'ifAdminStatus' => 'AdminStatus', 'ifOperStatus' => 'OperStatus'];
            foreach (['In', 'Out'] as $direction) {
                foreach (['Octets' => 'Octets', 'UcastPkts' => 'Packets', 'MulticastPkts' => 'MulticastPackets', 'BroadcastPkts' => 'BroadcastPackets'] as $field => $suffix) {
                    $columns['ifHC' . $direction . $field] = 'StatisticsEntry' . $direction . $suffix;
                }
            }
            $map = array_map(fn ($column) => $type . $column, $columns);
            $oids = ["CORIANT-GROOVE-MIB::{$type}Entry"];
            if ($knownPorts !== null) {
                $oids[] = "CORIANT-GROOVE-MIB::{$type}Statistics";
            }
            foreach ($this->portTable($oids, true) as $key => $row) {
                $parts = explode('.', $key);
                if (count($parts) !== 5 || ! isset($row[$type . 'AdminStatus'])) {
                    continue;
                }
                $index = (int) vsprintf('%03d%03d%03d%03d%03d', $parts);
                $name = (str_starts_with($type, 'eth') ? $speed . 'gbe' : $type) . "-$parts[0]/$parts[1]/$parts[3]";
                $name .= $speed < 40 ? '.' . $parts[4] : '';
                $ports[$index] = ['ifName' => $name, 'ifDescr' => $name, 'ifHighSpeed' => $speed * 1000, 'ifType' => 'Ethernet']
                    + $this->mapPortFields($row, $map);
            }
        }

        return $ports;
    }
}

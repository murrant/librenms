<?php
/**
 * Vrf.php
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
 * @link       https://www.librenms.org
 *
 * @copyright  2022 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Modules;

use Illuminate\Support\Collection;
use LibreNMS\DB\SyncsModels;
use LibreNMS\Interfaces\Discovery\VrfDiscovery;
use LibreNMS\Interfaces\Polling\VrfPolling;
use LibreNMS\Interfaces\VrfPortDiscovery;
use LibreNMS\OS;
use Log;

class Vrf implements \LibreNMS\Interfaces\Module
{
    use SyncsModels;

    /**
     * @inheritDoc
     */
    public function discover(OS $os)
    {
        $device = $os->getDevice();
        if ($os instanceof VrfDiscovery) {
            $vrfs = $os->discoverVrfs();
        } else {
            $vrfs = $this->discoverVrfsViaMplsStd();
        }

        $vrfs = $this->syncModels($device, 'vrfs', $vrfs);

        if ($vrfs->isNotEmpty()) {
            $ports = $device->ports;
            if ($os instanceof VrfPortDiscovery) {
                $ports = $os->discoverVrfPorts($vrfs, $ports);
            } else {
                $ports = $this->discoverVrfPortsViaMplsStd($os, $vrfs, $ports);
            }
            $device->ports()->saveMany($ports);

            $ports = $ports->groupBy('ifVrf');
            foreach ($vrfs as $vrf) {
                Log::info(sprintf('VRF: %s (%s)  RD: %s', $vrf->vrf_name, $vrf->mplsVpnVrfDescription, $vrf->mplsL3VpnVrfRD));
                if ($ports->has($vrf->vrf_id)) {
                    Log::info('Ports: ' . $ports->get($vrf->vrf_id)->pluck('ifName')->implode(' '));
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function poll(OS $os)
    {
        $device = $os->getDevice();
        if ($os instanceof VrfPolling) {
            $this->syncModels($device, 'vrfs', $os->pollVrfs($device->vrfs));
        }
    }

    /**
     * @inheritDoc
     */
    public function cleanup(OS $os)
    {
        // TODO: Implement cleanup() method.
    }

    public function discoverVrfsViaMplsStd(): Collection
    {
        return \SnmpQuery::walk('MPLS-L3VPN-STD-MIB::mplsL3VpnVrfTable')->mapTable(function ($data, $vrf) {
            return new \App\Models\Vrf([
                'vrf_name' => trim($vrf, '"'),
                'mplsVpnVrfRouteDistinguisher' => $data['MPLS-L3VPN-STD-MIB::mplsL3VpnVrfRD'] ?? '',
                'mplsVpnVrfDescription' => $data['MPLS-L3VPN-STD-MIB::mplsL3VpnVrfDescription'] ?? '',
            ]);
        });
    }

    public function discoverVrfPortsViaMplsStd(OS $os, Collection $vrfs, Collection $ports): Collection
    {
        $data = \SnmpQuery::walk('MPLS-L3VPN-STD-MIB::mplsL3VpnIfVpnClassification')->table(1);
        $ports = $ports->keyBy('ifIndex');
        foreach ($vrfs as $vrf) {
            foreach ($data[$vrf->vrf_name]['MPLS-L3VPN-STD-MIB::mplsL3VpnIfVpnClassification'] ?? [] as $ifIndex => $classification) {
                $ports->get($ifIndex)->ifVrf = $vrf->vrf_id;
            }
        }

        return $ports;
    }
}

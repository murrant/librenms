<?php

/**
 * timos.inc.php
 *
 * LibreNMS bgp_peers for Timos
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
 * @copyright  2020 LibreNMS Contributors
 * @author     LibreNMS Contributors
 */

use LibreNMS\Config;

if (Config::get('enable_bgp') && $device['os'] == 'timos') {
    $bgpPeers = snmpwalk_group($device, 'tBgpPeerNgTable', 'TIMETRA-BGP-MIB', 3);
    \App\Observers\ModuleModelObserver::observe(\App\Models\BgpPeer::class);
    $valid_peers = [];

    foreach ($bgpPeers as $vrfOid => $vrf) {
        $vrf_id = \App\Models\Vrf::where('vrf_oid', $vrfOid)->value('vrf_id');
        foreach ($vrf as $family => $family_data) {
            foreach ($family_data as $address => $value) {
                $peer = \App\Models\BgpPeer::firstOrNew([
                    'device_id' => $device['device_id'],
                    'vrf_id' => $vrf_id,
                    'bgpPeerIdentifier' => $address,
                ], [
                    'bgpPeerState' => 'idle',
                    'bgpPeerRemoteAddr' => '0.0.0.0',
                    'bgpPeerInUpdates' => 0,
                    'bgpPeerOutUpdates' => 0,
                    'bgpPeerInTotalMessages' => 0,
                    'bgpPeerOutTotalMessages' => 0,
                    'bgpPeerFsmEstablishedTime' => 0,
                    'bgpPeerInUpdateElapsedTime' => 0,
                ]);
                $peer->bgpLocalAddr = $value['tBgpPeerNgLocalAddress'] ?: '0.0.0.0';
                $peer->bgpPeerRemoteAs = $value['tBgpPeerNgPeerAS4Byte'];
                $peer->bgpPeerAdminStatus = $value['tBgpPeerNgShutdown'] == '2' ? 'start' : 'stop';
                $peer->astext = get_astext($value['tBgpPeerNgPeerAS4Byte']);
                $new = ! $peer->exists;
                $peer->save();
                $valid_peers[] = $peer->bgpPeer_id;

                if ($new && Config::get('autodiscovery.bgp')) {
                    discover_new_device(gethostbyaddr($address), $device, 'BGP');
                }
            }
        }
    }

    // clean up peers
    \App\Models\BgpPeer::whereNotIn('bgpPeer_id', $valid_peers)->get()->each->delete();

    unset($bgpPeers);
    // No return statement here, so standard BGP mib will still be polled after this file is executed.
}

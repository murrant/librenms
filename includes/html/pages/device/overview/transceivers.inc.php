<?php
/**
 * transceivers.inc.php
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
 * @copyright  2024 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */
if (DeviceCache::getPrimary()->transceivers->isNotEmpty()) {
    DeviceCache::getPrimary()->transceivers->load(['port']);
    echo view('device.overview.transceivers', [
        'transceivers' => DeviceCache::getPrimary()->transceivers,
        'transceivers_link' => route('device', ['device' => DeviceCache::getPrimary()->device_id, 'tab' => 'ports', 'vars' => 'transceivers']),
        'sensors' => DeviceCache::getPrimary()->sensors->where('sensor_type', 'transceiver'),
        // only temp and rx power to reduce information overload, click through to see all
        'filterSensors' => fn ($s) => $s->sensor_class == 'temperature' || ($s->sensor_class == 'dbm' && str_contains(strtolower($s->sensor_descr), 'rx')),
    ]);
}

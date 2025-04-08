<?php

/**
 * EditSnmpController.php
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
 * @copyright  2025 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Http\Controllers\Device\Tabs\Edit;

use App\Facades\LibrenmsConfig;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LibreNMS\Enum\PortAssociationMode;
use LibreNMS\Polling\ConnectivityHelper;
use LibreNMS\SNMPCapabilities;

class EditSnmpController extends Controller
{
    public function __invoke(Device $device, Request $request)
    {
        $this->authorize('manage-device', $device);

//        dd($request->all());

        $values = $this->validate($request, [
            'force_save' => 'bool',
            'poller_group' => 'int',
            'snmp' => 'nullable|in:on',
            'snmpver' => 'in:v1,v2c,v3',
            'transport' => 'in:udp,tcp,udp6,tcp6',
            'port_association_mode' => Rule::in(array_keys(PortAssociationMode::getModes())),
            'max_repeaters' => 'nullable|int',
            'max_oid' => 'nullable|int',
            'retries' => 'nullable|int',
            'timeout' => 'nullable|int',
            'authalgo' => Rule::in(array_keys(SNMPCapabilities::authAlgorithms())),
            'authlevel' => 'in:noAuthNoPriv,authNoPriv,authPriv',
            'authname' => 'nullable|string',
            'authpass' => 'nullable|string',
            'cryptoalgo' => Rule::in(array_keys(SNMPCapabilities::cryptoAlgoritms())),
            'cryptopass' => 'nullable|string',
            'community' => 'string',
            'hardware' => 'string',
            'os' => Rule::in(array_keys(LibrenmsConfig::get('os', []))),
            'sysName' => 'string',
        ]);

        $this->applyValues($device, $values);
        $device->save();
    }

    private function applyValues(Device $device, array $values): Device
    {
        $force_save = isset($values['force_save']) && $values['force_save'] == 'on';

        $device->fill($values);

        $device->snmp_disable = (isset($values['snmp']) && $values['snmp'] == 'on') ? 0 : 1;
        $device->poller_group ??= 0;
        $device->port = $device->port ?: Config::get('snmp.port');
        $device->transport = $device->transport ?: 'udp';

        if (! $force_save && ! $device->snmp_disable) {
            if (! (new ConnectivityHelper($device))->isSNMPable()) {
                $message = "Could not connect to $device->hostname with those SNMP settings.  To save anyway, turn on Force Save.";
                throw ValidationException::withMessages([$message]);
            }
        }

        $this->updateDeviceAttribute($device, 'snmp_max_repeaters', $values['max_repeaters']);
        $this->updateDeviceAttribute($device, 'snmp_max_oid', $values['max_oid']);

        if ($device->save()) {
            toast()->success('Device record updated');
        } else {
            toast()->info('SNMP settings did not change');
        }

        return $device;
    }

    /**
     * Update a device attribute if the form value differs from the current value
     */
    protected function updateDeviceAttribute(Device $device, string $attribute, mixed $formValue): bool
    {
        if ($formValue == $device->getAttrib($attribute)) {
            return true;
        }

        if (is_numeric($formValue) && $formValue != 0) {
            return $device->setAttrib($attribute, $formValue);
        }

        return $device->forgetAttrib($attribute);
    }
}

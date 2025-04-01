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

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Validation\Rule;
use LibreNMS\Enum\PortAssociationMode;

class EditSnmpController extends Controller
{
    public function __invoke(Device $device, Request $request)
    {
        $this->authorize('manage-device', $device);

        $values = $this->validate($request, [
            'force_save' => 'bool',

            'poller_group' => 'int',
            'snmp' => 'bool',
            'transport' => 'in:udp,tcp,udp6,tcp6',
            'port_association_mode' => Rule::in(PortAssociationMode::getModes()),
            'max_repeaters' => 'int',
            'max_oid' => 'int',
            'retries' => 'int',
            'timeout' => 'int',
            'authalgo' => '',
            'authlevel' => '',
            'authname' => '',
            'cryptoalgo' => '',
            'cryptopass' => '',
            'community' => 'string',
            'hardware' => 'string',
            'os' => 'string',
            'sysName' => 'string',
        ]);

        $device->fill($values);
        $device->snmp_disable = $request->get('snmp') !== 'on';
        $device->save();
    }
}

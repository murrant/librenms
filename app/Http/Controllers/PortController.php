<?php
/*
 * PortController.php
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2021 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Http\Controllers;

use App\Models\Port;
use Illuminate\Support\Facades\Validator;
use LibreNMS\Enum\Severity;

class PortController extends Controller
{
    public function update(\Illuminate\Http\Request $request, Port $port)
    {
        $validated = Validator::make($request->json()->all(), [
            'groups' => 'sometimes|required|array',
            'groups.*' => 'integer',
            'speed' => function ($attribute, $value, $fail) {
                if ($value && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $fail(trans('validation.integer', ['attribute' => $attribute]));
                }
            },
            'descr' => 'sometimes|string',
        ])->validate();

        if (empty($validated)) {
            throw new AjaxUpdateException(trans('ports.no_data'));
        }

        $messages = [];

        if (array_key_exists('groups', $validated)) {
            $messages[] = $this->handleGroupUpdate($port, $validated['groups']);
        }

        if (array_key_exists('speed', $validated)) {
            $messages[] = $this->handleSpeedUpdate($port, $validated['speed']);
        }

        if (array_key_exists('descr', $validated)) {
            $messages[] = $this->handleDescrUpdate($port, $validated['descr']);
        }


        return response()->json(['message' => implode(PHP_EOL, $messages)]);
    }

    /**
     * @throws AjaxUpdateException
     */
    private function handleGroupUpdate(Port $port, array $groups): string
    {
        $changes = $port->groups()->sync($gorups);
        $groups_updated = array_sum(array_map(function ($group_ids) {
            return count($group_ids);
        }, $changes));

        if ($groups_updated > 0) {
            return trans('port.groups.updated', ['port' => $port->getLabel()]);
        }

        throw new AjaxUpdateException(trans('port.groups.none', ['port' => $port->getLabel()]));
    }

    /**
     * @throws AjaxUpdateException
     */
    private function handleSpeedUpdate(Port $port, int $speed): string
    {
        if (empty($port->ifName)) {
            throw new AjaxUpdateException(trans('port.speed.ifName_missing'));
        }

        if (empty($speed)) {
            $port->device->forgetAttrib('ifSpeed:' . $port->ifName);
            $message = trans('port.speed.cleared', ['name' => $port->ifName]);
            \App\Models\Eventlog::log($message, $port->device, 'interface', Severity::Notice, $port->port_id);
            
            return $message;
        }

        $port->ifSpeed = $speed;

        if ($port->save()) {
            $port->device->setAttrib('ifSpeed:' . $port->ifName, $speed);
            $message = trans('port.speed.updated', ['name' => $port->ifName, 'speed' => $speed]);
            \App\Models\Eventlog::log($message, $port->device, 'interface', Severity::Notice, $port->port_id);

            // handle rrdtune
            $port_tune = $port->device->getAttrib('ifName_tune:' . $port->ifName);
            $device_tune = $port->device->getAttrib('override_rrdtool_tune');
            if ($port_tune == 'true' ||
                ($device_tune == 'true' && $port_tune != 'false') ||
                (\LibreNMS\Config::get('rrdtool_tune') == 'true' && $port_tune != 'false' && $device_tune != 'false')) {
                $rrdfile = get_port_rrdfile_path($port->device->hostname, $port->port_id);
                Rrd::tune('port', $rrdfile, $speed);
            }

            return $message;
        }

        throw new AjaxUpdateException(trans('port.speed.none', ['name' => $port->ifName]));
    }

    /**
     * @throws AjaxUpdateException
     */
    private function handleDescrUpdate(Port $port, string $descr)
    {
        if (empty($port->ifName)) {
            throw new AjaxUpdateException(trans('port.descr.ifName_missing'));
        }

        if (empty($descr)) {
            $port->device->forgetAttrib('ifName:' . $port->ifName);
            $message = trans('port.descr.cleared', ['name' => $port->ifName]);
            \App\Models\Eventlog::log($message, $port->device, 'interface', Severity::Notice, $port->port_id);
            
            return $message;
        }

        $port->ifAlias = $descr;

        if ($port->save()) {
            $port->device->setAttrib('ifName:' . $port->ifName, $descr);
            $message = trans('port.descr.updated', ['name' => $port->ifName, 'descr' => $descr]);
            \App\Models\Eventlog::log($message, $port->device, 'interface', Severity::Notice, $port->port_id);

            return $message;
        }

        throw new AjaxUpdateException(trans('port.descr.none', ['name' => $port->ifName]));
    }
}

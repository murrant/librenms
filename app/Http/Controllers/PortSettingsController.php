<?php
/*
 * PortSettingsController.php
 *
 * Manage port settings
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

use App\Facades\Rrd;
use App\Models\Eventlog;
use App\Models\Port;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LibreNMS\Config;
use LibreNMS\Enum\Severity;
use LibreNMS\Exceptions\AjaxUpdateException;
use LibreNMS\Util\Number;

class PortSettingsController extends Controller
{
    public function index(\App\Models\Device $device)
    {
        return view('port.settings.index', [
            'device' => $device,
        ]);
    }

    /**
     * @throws AjaxUpdateException
     * @throws ValidationException
     */
    public function update(Request $request, Port $port): JsonResponse
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
            'disabled' => 'sometimes|bool',
            'ignore' => 'sometimes|bool',
            'port_tune' => 'sometimes|bool',

        ])->validate();

        if (empty($validated)) {
            throw new AjaxUpdateException(trans('port.no_data'));
        }

        $messages = [];

        if (array_key_exists('groups', $validated)) {
            $messages[] = $this->handleGroupUpdate($port, $validated['groups']);
        }

        if (array_key_exists('descr', $validated)) {
            $descr = $validated['descr'];
            $messages[] = $this->updateAttrib($port, $descr, 'ifName');
            if ($descr) {
                $port->ifAlias = $descr;
            }
        }

        if (array_key_exists('port_tune', $validated)) {
            $messages[] = $this->updateAttrib($port, $validated['port_tune'], 'ifName_tune');
        }

        if (array_key_exists('speed', $validated)) {
            $speed = $validated['speed'];
            $messages[] = $this->updateAttrib($port, $speed,'ifSpeed');
            if ($speed) {
                $port->ifSpeed = $speed;
            }
        }

        if (array_key_exists('disabled', $validated)) {
            $port->disabled = $validated['disabled'];
            $messages[] = $port->disabled
                ? trans('port.disabled.enabled', ['name' => $port->ifName])
                : trans('port.disabled.disabled', ['name' => $port->ifName]);
        }

        if (array_key_exists('ignore', $validated)) {
            $port->ignore = $validated['ignore'];
            $messages[] = $port->ignore
                ? trans('port.ignore.enabled', ['name' => $port->ifName])
                : trans('port.ignore.disabled', ['name' => $port->ifName]);
        }

        // check rrdtune if speed or port_tune changed
        if ($port->isDirty(['ifSpeed']) || array_key_exists('port_tune', $validated)) {
            $port_tune = $port->device->getAttrib('ifName_tune:' . $port->ifName);
            $device_tune = $port->device->getAttrib('override_rrdtool_tune');
            if ($port_tune == 'true' ||
                ($device_tune == 'true' && $port_tune != 'false') ||
                (Config::get('rrdtool_tune') == 'true' && $port_tune != 'false' && $device_tune != 'false')) {

                $rrd_file = Rrd::name($port->device->hostname, Rrd::portName($port->port_id));
                Rrd::tune('port', $rrd_file, $port->ifSpeed);
                $messages[] = trans('port.tuned', ['name' => $port->ifName, 'rate' => Number::formatSi($port->ifSpeed, 2, 3, 'bps')]);
            }
        }

        $port->save();

        return response()->json(['message' => implode('<br />', $messages)]);
    }

    /**
     * @throws AjaxUpdateException
     */
    private function handleGroupUpdate(Port $port, array $groups): string
    {
        $changes = $port->groups()->sync($groups);
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
    private function updateAttrib(Port $port, int|string|bool $value, string $attribPrefix): string
    {
        if (empty($port->ifName)) {
            throw new AjaxUpdateException(trans('port.' . $attribPrefix . '.ifName_missing'));
        }

        $translate_data =  ['name' => $port->ifName, 'value' => $value];
        if (is_numeric($value)) {
            $translate_data['rate'] = Number::formatSi($value, suffix: 'bps');
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        $name = $attribPrefix . ':' . $port->ifName;

        if ($value === '' || $value === '0' || $value === 0) {
            $port->device->forgetAttrib($name);
            $message = trans('port.' . $attribPrefix . '.cleared', ['name' => $port->ifName]);
            Eventlog::log($message, $port->device, 'interface', Severity::Notice, $port->port_id);

            return $message;
        }

        $current = $port->device->getAttrib($name);
        if ($value != $current) {

            $port->device->setAttrib($name, $value);
            $suffix = $value === 'false' ? '.cleared' : '.updated';
            $message = trans('port.' . $attribPrefix . $suffix, $translate_data);
            Eventlog::log($message, $port->device, 'interface', Severity::Notice, $port->port_id);

            return $message;
        }

        throw new AjaxUpdateException(trans('port.' . $attribPrefix . '.no_change', ['name' => $port->ifName]));
    }
}

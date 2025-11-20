<?php
/**
 * AlertRuleController.php
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

namespace App\Http\Controllers\Table;

use App\Models\AlertRule;
use App\Models\AlertTransport;
use Illuminate\Http\Request;
use LibreNMS\Alerting\QueryBuilderParser;
use LibreNMS\Enum\AlertState;

class AlertRuleController extends TableController
{
    private ?array $defaultAlertRules = null;

    protected function rules()
    {
        return [
            'device' => 'nullable|int',
        ];
    }

    protected function searchFields($request)
    {
        return ['name'];
    }

    protected function sortFields($request)
    {
        return ['id', 'name', 'severity', 'disabled', 'invert_map'];
    }

    /**
     * @inheritDoc
     */
    protected function baseQuery(Request $request)
    {
        return AlertRule::query()
            ->when($request->get('device'), fn($query, $device_id) => $query->where(function ($query) use ($device_id): void {
                // Rules where invert_map is false/null (normal behavior)
                $query->where(fn($query) => $query->where(function ($query): void {
                    $query->where('invert_map', false)
                        ->orWhereNull('invert_map');
                })->where(fn($query) =>
                // Match if device is in any relationship OR no relationships exist
                    $query->whereHas('devices', fn ($q) => $q->where('devices.device_id', $device_id))
                    ->orWhereHas('groups', fn ($q) => $q->whereHas('devices', fn ($q) => $q->where('devices.device_id', $device_id)))
                    ->orWhereHas('locations', fn ($q) => $q->whereHas('devices', fn ($q) => $q->where('devices.device_id', $device_id)))
                    ->orWhere(fn($query) => $query->whereDoesntHave('devices')
                        ->whereDoesntHave('groups')
                        ->whereDoesntHave('locations'))))
                // Rules where invert_map is true (inverted behavior)
                ->orWhere(fn($query) => $query->where('invert_map', true)
                    ->where(fn($query) =>
                        // Must have at least one relationship
                        $query->has('devices')
                        ->orHas('groups')
                        ->orHas('locations'))
                    ->whereDoesntHave('devices', fn ($q) => $q->where('devices.device_id', $device_id))
                    ->whereDoesntHave('groups', fn ($q) => $q->whereHas('devices', fn ($q) => $q->where('devices.device_id', $device_id)))
                    ->whereDoesntHave('locations', fn ($q) => $q->whereHas('devices', fn ($q) => $q->where('devices.device_id', $device_id))));
            }))->with([
                'alerts' => fn ($query) => $query->select(['id', 'rule_id', 'state']),
                'devices' => fn ($query) => $query->select(['devices.device_id', 'hostname', 'display', 'sysName']),
                'groups' => fn ($query) => $query->select(['device_groups.id', 'name']),
                'locations' => fn ($query) => $query->select(['locations.id', 'location']),
                'transportSingles' => fn ($query) => $query->select(['alert_transports.transport_id', 'transport_name']),
                'transportGroups' => fn ($query) => $query->select(['alert_transport_groups.transport_group_id', 'transport_group_name']),
            ]);
    }

    /**
     * @param  AlertRule  $alertRule
     * @return array
     */
    public function formatItem($alertRule): array
    {
        return [
            'id' => $alertRule->id,
            'name' => $alertRule->name,
            'severity' => $alertRule->severity,
            'disabled' => $alertRule->disabled,
            'invert_map' => $alertRule->invert_map,
            'extra' => $alertRule->extra,
            'devices' => $this->describeDevices($alertRule),
            'transports' => $this->describeTransports($alertRule),
            'rule' => QueryBuilderParser::fromJson($alertRule->builder)->toSql(false),
            'state' => $alertRule->alerts->first()->state ?? AlertState::CLEAR,
        ];
    }

    private function describeDevices(AlertRule $alertRule): array
    {
        $devices = [];

        foreach($alertRule->devices as $device) {
            $devices[] = [
                'type' => 'device',
                'id' => $device->device_id,
                'name' => $device->displayName(),
                'url' => route('device', ['device' => $device->device_id]),
            ];
        }

        foreach($alertRule->groups as $group) {
            $devices[] = [
                'type' => 'group',
                'id' => $group->id,
                'name' => $group->name,
                'url' => route('device-groups.show', ['device_group' => $group->id]),
            ];
        }

        foreach($alertRule->locations as $location) {
            $devices[] = [
                'type' => 'location',
                'id' => $location->id,
                'name' => $location->location,
                'url' => url('devices/location=' . $location->id),
            ];
        }

        return $devices;
    }

    private function describeTransports(AlertRule $alertRule): array
    {
        $transports = [];

        foreach($alertRule->transportSingles as $transport) {
            $transports[] = [
                'type' => 'single',
                'id' => $transport->transport_id,
                'name' => $transport->transport_name,
            ];
        }

        foreach($alertRule->transportGroups as $transport) {
            $transports[] = [
                'type' => 'group',
                'id' => $transport->transport_group_id,
                'name' => $transport->transport_group_name,
            ];
        }

        if (empty($transports)) {
            return $this->defaultAlertRules();
        }

        return $transports;
    }

    private function defaultAlertRules(): array
    {
        return $this->defaultAlertRules ?? AlertTransport::where('is_default', true)->get()->map(fn ($transport) => [
            'type' => 'default',
            'id' => $transport->transport_id,
            'name' => $transport->transport_name,
        ])->all();
    }
}

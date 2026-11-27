<?php

namespace LibreNMS\Ports;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use App\Models\Port;
use App\Models\PortGroup;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LibreNMS\Util\Mac;
use LibreNMS\Util\StringHelpers;

final readonly class PortInventory
{
    /** @param array<int, array> $rows Interfaces keyed by ifIndex. */
    public function __construct(public array $rows, public bool $complete = true, private string $association = 'ifIndex')
    {
    }

    public function sync(Device $device): void
    {
        if ($this->rows === []) {
            return;
        }
        DB::transaction(function () use ($device): void {
            Device::whereKey($device->device_id)->lockForUpdate()->firstOrFail(['device_id']);
            $ports = $device->ports()->orderByDesc('deleted')->orderBy('port_id')->get();
            $byIndex = $ports->keyBy('ifIndex');
            $byName = $ports->keyBy('ifName');
            $missing = $ports->where('deleted', false)->keyBy('port_id');
            $byIdentity = $this->association === 'ifIndex' ? null : $ports->groupBy($this->association)
                ->map(fn ($group) => $group->keyBy('ifIndex'));
            $filter = new PortFilter($device->os);
            $parser = new PortDescription;
            $groupId = LibrenmsConfig::get('default_port_group');
            $group = $groupId ? PortGroup::find($groupId) : null;
            $seen = [];
            foreach ($this->rows as $index => $row) {
                if (! ctype_digit((string) $index)) {
                    continue;
                }
                $data = $this->metadata($row);
                if (! array_filter(Arr::only($data, ['ifName', 'ifDescr', 'ifAlias']), fn ($value) => $value !== null && $value !== '')) {
                    $missing->forget($byIndex->get($index)?->port_id);
                    continue;
                }
                if (! $filter->accepts($data + $row)) {
                    continue;
                }
                $name = $data['ifName'] === '' ? $data['ifDescr'] : $data['ifName'];
                $override = $device->getAttrib("ifName:$name");
                $data['ifName'] = $name;
                $data['ifDescr'] = $data['ifDescr'] === '' ? $name : $data['ifDescr'];
                $alias = $override === '1' ? $byName->get($name)?->ifAlias : $override;
                if (! empty($row['keepStoredAlias'])) {
                    $alias ??= $byIndex->get($index)?->ifAlias;
                }
                $data['ifAlias'] = $alias ?? ($data['ifAlias'] === '' ? $data['ifDescr'] : $data['ifAlias']);
                $data['ifIndex'] = $index;
                $key = $data[$this->association];
                $port = ($byIdentity === null || $key === '') ? $byIndex->get($index)
                    : $this->matchIdentity($byIdentity->get($key), $index, $key);
                if ($port && isset($seen[$port->port_id])) {
                    $port = null;
                }
                if (! $port && $key === '' && LibrenmsConfig::get('ignore_unmapable_port')) {
                    continue;
                }
                $port ??= new Port(['device_id' => $device->device_id]);
                $port->fill($data);
                if ($port->deleted) {
                    $port->deleted = false;
                }
                if (! $port->exists) {
                    $port->fill(Arr::only($row, ['ifOperStatus', 'ifAdminStatus']));
                    $port->save(); // Custom parsers can request the assigned port ID.
                    $group?->ports()->attach($port->port_id);
                }
                $parser->apply($port, $device, $row, $override);
                if ($port->isDirty()) {
                    $port->save();
                }
                $seen[$port->port_id] = true;
                $missing->forget($port->port_id);
            }
            if ($this->complete && $missing->isNotEmpty()) {
                $device->ports()->whereIntegerInRaw('port_id', $missing->keys())->update(['deleted' => true]);
            }
        });
    }

    /** @param Collection<int, Port>|null $candidates */
    private function matchIdentity(?Collection $candidates, int $index, string $key): ?Port
    {
        if ($candidates === null || $candidates->has($index) || $candidates->count() !== 1) {
            return $candidates?->get($index);
        }

        // The index moved. Reserve the original port if another row still reports its identity there.
        $oldIndex = $candidates->keys()->first();

        return ($this->rows[$oldIndex][$this->association] ?? null) === $key ? null : $candidates->first();
    }

    /** @return array<model-property<Port>, mixed> */
    private function metadata(array $row): array
    {
        $data = [];
        foreach (['ifName', 'ifAlias', 'ifDescr', 'ifType', 'ifMtu', 'ifPhysAddress', 'ifConnectorPresent',
            'ifTrunk', 'ifVlan', 'pagpOperationMode', 'pagpPortState', 'pagpPartnerDeviceId', 'pagpPartnerLearnMethod',
            'pagpPartnerIfIndex', 'pagpPartnerGroupIfIndex', 'pagpPartnerDeviceName', 'pagpEthcOperationMode', 'pagpDeviceId', 'pagpGroupIfIndex'] as $field) {
            if (array_key_exists($field, $row)) {
                $data[$field] = $row[$field];
            }
        }
        foreach (['ifName', 'ifAlias', 'ifDescr'] as $field) {
            $data[$field] = trim(StringHelpers::inferEncoding($data[$field] ?? '') ?? '');
        }
        if (! is_numeric($data['ifMtu'] ?? null)) {
            unset($data['ifMtu']);
        }
        if (isset($data['ifPhysAddress'])) {
            $data['ifPhysAddress'] = Mac::parseSnmp($data['ifPhysAddress'])->hex();
        }
        if (isset($data['ifConnectorPresent']) && ! in_array($data['ifConnectorPresent'], ['true', 'false'], true)) {
            $data['ifConnectorPresent'] = null;
        }

        return $data;
    }
}

<?php

namespace LibreNMS\OS\Traits;

use App\Facades\LibrenmsConfig;
use LibreNMS\Ports\PortInventory;
use LibreNMS\Ports\PortMeasurement;
use SnmpQuery;

trait IfMib
{
    private const array HIGH_CAPACITY_COUNTERS = [
        'ifInOctets' => 'ifHCInOctets',
        'ifInUcastPkts' => 'ifHCInUcastPkts',
        'ifInMulticastPkts' => 'ifHCInMulticastPkts',
        'ifInBroadcastPkts' => 'ifHCInBroadcastPkts',
        'ifOutOctets' => 'ifHCOutOctets',
        'ifOutUcastPkts' => 'ifHCOutUcastPkts',
        'ifOutMulticastPkts' => 'ifHCOutMulticastPkts',
        'ifOutBroadcastPkts' => 'ifHCOutBroadcastPkts',
    ];

    public function discoverPorts(): PortInventory
    {
        return new PortInventory($this->collectPortRows(null, false), association: $this->portAssociationMode());
    }

    /**
     * Collect rows for discovery ($knownPorts is null) or for a poll of the supplied interfaces.
     *
     * @param  array<int, \App\Models\Port>|null  $knownPorts
     */
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        return $knownPorts === null
            ? $this->discoverIfMibRows(['Q-BRIDGE-MIB::dot1qPvid', 'EtherLike-MIB::dot3StatsDuplexStatus'])
            : $this->pollIfMibRows($knownPorts, $selected, $this->etherLikeOids($selected));
    }

    protected function discoverIfMibRows(array $extraOids): array
    {
        $ifX = $this->hasBrokenIfXEntry()
            ? ['ifName', 'ifAlias', 'ifConnectorPresent', 'ifInMulticastPkts', 'ifOutMulticastPkts', 'ifInBroadcastPkts', 'ifOutBroadcastPkts']
            : ['ifXTable'];
        $rows = $this->portTable([...$this->ifMibOids(['ifTable', ...$ifX]), ...$extraOids], true);
        $available = [];
        $bridgeIndexes = null;
        foreach ($rows as $bridgePort => $row) {
            $available += $row;
            if (isset($row['dot1qPvid'])) {
                $bridgeIndexes ??= $this->bridgePortIfIndexes();
                $index = $bridgeIndexes[$bridgePort] ?? $bridgePort;
                if (isset($rows[$index])) {
                    $rows[$index]['ifVlan'] = $row['dot1qPvid'];
                }
            }
        }
        // A counter may only exist on a subset of interfaces.
        $supported = implode(',', array_intersect($this->portMetricColumns(), array_keys($available)));
        $device = $this->getDevice();
        if ($supported !== '' && $supported !== $device->getAttrib('ports_if_mib_columns')) {
            $device->setAttrib('ports_if_mib_columns', $supported);
        }

        return $rows;
    }

    public function pollPorts(array $ports, bool $selected): array
    {
        if ($ports === []) {
            return [];
        }
        $rows = array_intersect_key($this->collectPortRows($ports, $selected), $ports);
        $association = $this->portAssociationMode();
        if ($association === 'ifIndex') {
            return $rows;
        }

        // An ifIndex can be reused between discoveries. Wait for discovery to associate the new interface.
        foreach ($rows as $index => $row) {
            $identity = $row[$association] ?? null;
            if ($association === 'ifAlias') {
                $override = $this->getDevice()->getAttrib('ifName:' . ($row['ifName'] ?? ''));
                $identity = $override === '1' ? $ports[$index]->ifAlias : ($override ?? $identity);
            }
            if (($identity ?? '') === '') {
                $identity = $row[$association === 'ifDescr' ? 'ifName' : 'ifDescr'] ?? '';
            }
            if (trim((string) $identity) !== (string) $ports[$index]->getRawOriginal($association)) {
                unset($rows[$index]);
            }
        }

        return $rows;
    }

    protected function pollIfMibRows(array $knownPorts, bool $selected, array $extraOids): array
    {
        $identity = $this->portIdentityColumns();
        $stored = $this->getDevice()->getAttrib('ports_if_mib_columns');
        $columns = $this->portMetricColumns();
        if ($stored) {
            $columns = array_intersect($columns, explode(',', $stored));
        }
        $rows = $selected
            ? $this->selectedPortData($knownPorts, $identity, $columns, $extraOids)
            : $this->regularPortData(array_keys($knownPorts), [...$identity, ...$columns], $extraOids, (bool) $stored);
        $rows = array_intersect_key($rows, $knownPorts);
        $metadata = ! $identity ? ['ifName', 'ifDescr', 'ifType', 'ifConnectorPresent'] : ['ifType', 'ifConnectorPresent'];
        foreach ($rows as $index => &$row) {
            $row += $knownPorts[$index]->only($metadata);
        }

        return $rows;
    }

    protected function portIdentityColumns(): array
    {
        return match ($this->portAssociationMode()) {
            'ifIndex' => [],
            'ifAlias' => ['ifName', 'ifDescr', 'ifAlias'],
            default => ['ifName', 'ifDescr'],
        };
    }

    protected function portAssociationMode(): string
    {
        return $this->getDevice()->polling()->snmp()->portAssociationMode;
    }

    private function hasBrokenIfXEntry(): bool
    {
        $hardware = strtolower($this->getDevice()->hardware ?? '');

        return in_array($hardware, array_map(strtolower(...), (array) LibrenmsConfig::getOsSetting($this->getName(), 'bad_ifXEntry', [])), true);
    }

    protected function ifMibOids(array $columns): array
    {
        return array_map(fn ($field) => "IF-MIB::$field", $columns);
    }

    protected function portMetricColumns(): array
    {
        $columns = ['ifOperStatus', 'ifAdminStatus', 'ifLastChange', ...PortMeasurement::COUNTERS];
        if ($this->hasBrokenIfXEntry() || $this->getDevice()->polling()->snmp()->version === 'v1') {
            return $columns;
        }

        return [...$columns, ...array_values(self::HIGH_CAPACITY_COUNTERS)];
    }

    private function regularPortData(array $indexes, array $columns, array $extraOids, bool $columnsDiscovered): array
    {
        $snmp = $this->getDevice()->polling()->snmp();
        if ($columnsDiscovered && (count($indexes) === 1 || ($snmp->version === 'v1' && count($indexes) <= $snmp->maxOid))) {
            $rows = $this->getPortColumns($indexes, $this->ifMibOids($columns));

            return $extraOids ? array_replace_recursive($rows, $this->portTable($extraOids, true)) : $rows;
        }

        // Prefer the 64-bit column when the device supports both widths.
        $fallbacks = array_intersect(self::HIGH_CAPACITY_COUNTERS, $columns);
        $fallbacks = array_intersect_key($fallbacks, array_flip($columns));
        $primary = array_diff($columns, array_keys($fallbacks));
        $rows = $this->portTable([...$this->ifMibOids($primary), ...$extraOids], true);
        // A device may implement 64-bit counters on only some of its interfaces.
        $fallback = [];
        foreach ($fallbacks as $field => $hc) {
            if (array_any($indexes, fn ($index) => ! ctype_digit((string) ($rows[$index][$hc] ?? '')))) {
                $fallback[] = $field;
            }
        }

        return $fallback ? array_replace_recursive($rows, $this->portTable($this->ifMibOids($fallback), true)) : $rows;
    }

    private function selectedPortData(array $selected, array $identity, array $columns, array $extraOids): array
    {
        $rows = $this->getPortColumns(array_keys($selected), $this->ifMibOids(['ifOperStatus', 'ifAdminStatus', ...$identity]));
        $active = [];
        foreach ($selected as $index => $port) {
            $row = $rows[$index] ?? [];
            $admin = $row['ifAdminStatus'] ?? null;
            $oper = $row['ifOperStatus'] ?? null;
            $adminStillDown = $admin === 'down' && $admin === $port->getRawOriginal('ifAdminStatus');
            $operStillDown = in_array($oper, ['down', 'lowerLayerDown'], true) && $oper === $port->getRawOriginal('ifOperStatus');
            if ($port->poll_time && ($adminStillDown || $operStillDown)) {
                $rows[$index]['skipCounters'] = true;
            } else {
                $active[] = $index;
            }
        }
        if (! $active) {
            return $rows;
        }
        $fields = array_diff($columns, ['ifOperStatus', 'ifAdminStatus']);
        $details = $this->getPortColumns($active, [...$this->ifMibOids($fields), ...$extraOids]);

        return array_replace_recursive($rows, $details);
    }

    private function getPortColumns(array $indexes, array $columns): array
    {
        $rows = [];
        if (! $columns) {
            return $rows;
        }
        $query = SnmpQuery::device($this->getDevice())->hideMib()->enumStrings();
        // Bound response aggregation as well as the number of OIDs in each SNMP packet.
        foreach (array_chunk($indexes, 16) as $chunk) {
            $oids = [];
            foreach ($chunk as $index) {
                foreach ($columns as $column) {
                    $oids[] = "$column.$index";
                }
            }
            $query->get($oids)->throwIfConnectionError()->valuesByIndex($rows);
        }

        return $rows;
    }

    private function etherLikeOids(bool $selected): array
    {
        if (! LibrenmsConfig::get('enable_ports_etherlike')) {
            return [];
        }
        if (! $selected) {
            return ['EtherLike-MIB::dot3StatsTable'];
        }

        return array_map(fn ($field) => 'EtherLike-MIB::dot3Stats' . $field, ['Index', ...PortMeasurement::ETHERLIKE]);
    }

    protected function portTable(string|array $oids, bool $enumStrings = false): array
    {
        $query = SnmpQuery::device($this->getDevice())->hideMib();
        if ($enumStrings) {
            $query->enumStrings();
        }

        $response = $query->walk($oids)->throwIfConnectionError();
        if ($response->exitCode !== 0) {
            throw new \LibreNMS\Exceptions\SnmpException($response->getErrorMessage());
        }

        return $response->valuesByIndex();
    }

    /** Map available vendor columns to IF-MIB names, retaining genuine zeroes. */
    protected function mapPortFields(array $row, array $columns): array
    {
        $fields = [];
        foreach ($columns as $field => $column) {
            if (isset($row[$column])) {
                $fields[$field] = $row[$column];
            }
        }

        return $fields;
    }
}

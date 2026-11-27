<?php

namespace LibreNMS\OS\Traits;

use App\Facades\LibrenmsConfig;
use LibreNMS\Ports\PortInventory;
use LibreNMS\Ports\PortMeasurement;
use SnmpQuery;

trait IfMib
{
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
            ? $this->discoverIfMibRows(['Q-BRIDGE-MIB::dot1qPvid'])
            : $this->pollIfMibRows($knownPorts, $selected, $this->etherLikeOids($selected));
    }

    protected function discoverIfMibRows(array $extraOids): array
    {
        $rows = $this->portTable([
            ...$this->ifMibOids(['ifDescr', 'ifName', 'ifAlias', 'ifType', 'ifMtu', 'ifPhysAddress',
                'ifConnectorPresent', 'ifOperStatus', 'ifAdminStatus']),
            ...$extraOids,
        ], true);
        $bridgeIndexes = null;
        foreach ($rows as $bridgePort => $row) {
            if (isset($row['dot1qPvid'])) {
                $bridgeIndexes ??= $this->bridgePortIfIndexes();
                $index = $bridgeIndexes[$bridgePort] ?? $bridgePort;
                if (isset($rows[$index])) {
                    $rows[$index]['ifVlan'] = $row['dot1qPvid'];
                }
            }
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
        $byIndex = $this->portAssociationMode() === 'ifIndex';
        $identity = $byIndex ? [] : ['ifName', 'ifDescr', 'ifAlias'];
        $rows = $selected ? $this->selectedPortData($knownPorts, $identity, $extraOids) : $this->walkPortMetrics($identity, $extraOids);
        $rows = array_intersect_key($rows, $knownPorts);
        $metadata = $byIndex ? ['ifName', 'ifDescr', 'ifType', 'ifConnectorPresent'] : ['ifType', 'ifConnectorPresent'];
        foreach ($rows as $index => &$row) {
            $row += $knownPorts[$index]->only($metadata);
        }

        return $rows;
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

    private function ifMibOids(array $columns): array
    {
        return array_map(fn ($field) => "IF-MIB::$field", $columns);
    }

    private function walkPortMetrics(array $identity, array $extraOids): array
    {
        // A walk of ifTable can stop early on a malformed physical address. Walk live columns individually.
        $columns = [...$identity, 'ifOperStatus', 'ifAdminStatus', 'ifLastChange', 'ifSpeed',
            ...PortMeasurement::BASE, 'ifInNUcastPkts', 'ifOutNUcastPkts', 'ifInUnknownProtos', 'ifInDiscards', 'ifOutDiscards'];
        $oids = $this->ifMibOids($columns);
        if (! $this->hasBrokenIfXEntry()) {
            $oids[] = 'IF-MIB::ifXTable';
        }

        return $this->portTable([...$oids, ...$extraOids], true);
    }

    private function selectedPortData(array $selected, array $identity, array $extraOids): array
    {
        $rows = $this->getPortColumns(array_keys($selected), $this->ifMibOids(['ifOperStatus', 'ifAdminStatus', ...$identity]));
        $active = [];
        foreach ($selected as $index => $port) {
            $row = $rows[$index] ?? [];
            $admin = $row['ifAdminStatus'] ?? null;
            $oper = $row['ifOperStatus'] ?? null;
            if ($port->poll_time && (($admin === 'down' && $admin === $port->getRawOriginal('ifAdminStatus'))
                || (in_array($oper, ['down', 'lowerLayerDown'], true) && $oper === $port->getRawOriginal('ifOperStatus')))) {
                $rows[$index]['skipCounters'] = true;
            } else {
                $active[] = $index;
            }
        }
        if (! $active) {
            return $rows;
        }
        $fields = ['ifLastChange', 'ifSpeed', ...PortMeasurement::COUNTERS];
        if (! $this->hasBrokenIfXEntry()) {
            $fields[] = 'ifHighSpeed';
            foreach (['In', 'Out'] as $direction) {
                foreach (['Octets', 'UcastPkts', 'MulticastPkts', 'BroadcastPkts'] as $counter) {
                    $fields[] = "ifHC$direction$counter";
                }
            }
        }
        $details = $this->getPortColumns($active, [...$this->ifMibOids($fields), ...$extraOids]);

        return array_replace_recursive($rows, $details);
    }

    private function getPortColumns(array $indexes, array $columns): array
    {
        $rows = [];
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
            return ['EtherLike-MIB::dot3StatsDuplexStatus'];
        }
        if (! $selected) {
            return ['EtherLike-MIB::dot3StatsTable'];
        }

        return array_map(fn ($field) => 'EtherLike-MIB::dot3Stats' . $field, ['Index', 'DuplexStatus', ...PortMeasurement::ETHERLIKE]);
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

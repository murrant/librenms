<?php

namespace LibreNMS\OS\Traits;

use App\Facades\LibrenmsConfig;
use LibreNMS\Ports\PortInventory;
use LibreNMS\Ports\PortMeasurement;
use SnmpQuery;

trait IfMib
{
    protected const bool USE_IF_MIB = true;
    protected bool $portInventoryComplete = true;

    public function discoverPorts(): PortInventory
    {
        $this->portInventoryComplete = true;
        $rows = static::USE_IF_MIB ? $this->portTable([
            ...$this->ifMibOids(['ifDescr', 'ifName', 'ifAlias', 'ifType', 'ifMtu', 'ifPhysAddress',
                'ifConnectorPresent', 'ifOperStatus', 'ifAdminStatus']),
            ...$this->portInventoryOids(),
        ], true) : [];
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
        $this->addVendorPorts($rows);
        $this->discoverPortMetadata($rows);

        return new PortInventory($rows, $this->portInventoryComplete, $this->getDevice()->polling()->snmp()->portAssociationMode);
    }

    public function pollPorts(array $ports, bool $selected): array
    {
        if ($ports === []) {
            return [];
        }
        $association = $this->getDevice()->polling()->snmp()->portAssociationMode;
        $rows = [];
        if (static::USE_IF_MIB) {
            $identity = $association === 'ifIndex' ? [] : ['ifName', 'ifDescr', 'ifAlias'];
            $rows = $selected ? $this->selectedPortData($ports, $identity) : $this->walkPortMetrics($identity);
        }
        if ($association === 'ifIndex') {
            foreach (array_intersect_key($rows, $ports) as $index => $row) {
                $rows[$index] += $ports[$index]->only(['ifDescr', 'ifName', 'ifType', 'ifConnectorPresent']);
            }
        }
        $this->addVendorPorts($rows);
        if ($association !== 'ifIndex') {
            $this->discoverPortMetadata($rows);
        }
        $this->pollPortMetrics($rows);
        $rows = array_intersect_key($rows, $ports);
        // An ifIndex can be reused between discoveries. Wait for discovery to associate the new interface.
        if ($association !== 'ifIndex') {
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
        }
        if ($rows && LibrenmsConfig::get('enable_ports_poe')) {
            foreach ($this->collectPortPower($rows) as $index => $supply) {
                if (isset($rows[$index])) {
                    $rows[$index]['poe'] = $supply;
                }
            }
        }

        return $rows;
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

    private function walkPortMetrics(array $identity): array
    {
        // A walk of ifTable can stop early on a malformed physical address. Walk live columns individually.
        $columns = [...$identity, 'ifOperStatus', 'ifAdminStatus', 'ifLastChange', 'ifSpeed',
            ...PortMeasurement::BASE, 'ifInNUcastPkts', 'ifOutNUcastPkts', 'ifInUnknownProtos', 'ifInDiscards', 'ifOutDiscards'];
        $oids = $this->ifMibOids($columns);
        if (! $this->hasBrokenIfXEntry()) {
            $oids[] = 'IF-MIB::ifXTable';
        }

        return $this->portTable([...$oids, ...$this->portLinkOids(false)], true);
    }

    private function selectedPortData(array $selected, array $identity): array
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
        $details = $this->getPortColumns($active, [...$this->ifMibOids($fields), ...$this->portLinkOids(true)]);

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

    protected function portLinkOids(bool $selected): array
    {
        if (! LibrenmsConfig::get('enable_ports_etherlike')) {
            return ['EtherLike-MIB::dot3StatsDuplexStatus'];
        }
        if (! $selected) {
            return ['EtherLike-MIB::dot3StatsTable'];
        }

        return array_map(fn ($field) => 'EtherLike-MIB::dot3Stats' . $field, ['Index', 'DuplexStatus', ...PortMeasurement::ETHERLIKE]);
    }

    protected function portInventoryOids(): array
    {
        return ['Q-BRIDGE-MIB::dot1qPvid'];
    }

    /** Ports exposed through vendor tables during both discovery and polling. */
    protected function addVendorPorts(array &$ports): void
    {
    }

    protected function discoverPortMetadata(array &$ports): void
    {
    }

    protected function pollPortMetrics(array &$ports): void
    {
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

    /** Per-port PoE measurements in milliwatts, keyed by IF-MIB ifIndex. */
    protected function collectPortPower(array $ports): array
    {
        return [];
    }

    protected function portPowerFields(mixed $allocated, mixed $available, mixed $consumption, mixed $peak): array
    {
        return ['PortPwrAllocated' => $allocated, 'PortPwrAvailable' => $available,
            'PortConsumption' => $consumption, 'PortMaxPwrDrawn' => $peak];
    }
}

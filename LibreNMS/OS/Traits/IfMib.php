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
        $identity = ['ifDescr', 'ifName', 'ifAlias', 'ifType', 'ifMtu', 'ifPhysAddress', 'ifConnectorPresent'];
        if (LibrenmsConfig::getCombined($this->getName(), 'bad_ifoperstatus')) {
            $identity[] = 'ifOperStatus';
        }
        $rows = $this->portTable([...$this->ifMibOids($identity), ...$extraOids], true);
        if ($rows) {
            // GETNEXT at each column root finds sparse columns even when the first interface lacks them.
            $columns = $this->portMetricColumns();
            $probe = SnmpQuery::device($this->getDevice())->hideMib()->next($this->ifMibOids($columns))->throwIfConnectionError();
            if ($probe->exitCode === 0 && $probe->values()) {
                $available = [];
                foreach ($probe->valuesByIndex() as $row) {
                    $available += $row;
                }
                $supported = implode(',', array_intersect($columns, array_keys($available)));
                if ($supported !== '' && $supported !== $this->getDevice()->getAttrib('ports_if_mib_columns')) {
                    $this->getDevice()->setAttrib('ports_if_mib_columns', $supported);
                }
            }
        }
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
        $identity = $this->portIdentityColumns();
        $rows = $selected ? $this->selectedPortData($knownPorts, $identity, $extraOids) : $this->walkPortMetrics($knownPorts, $identity, $extraOids);
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
        $columns = ['ifOperStatus', 'ifAdminStatus', 'ifLastChange', 'ifSpeed', ...PortMeasurement::COUNTERS];
        if (! $this->hasBrokenIfXEntry()) {
            $columns[] = 'ifHighSpeed';
            if ($this->getDevice()->polling()->snmp()->version !== 'v1') {
                foreach (['In', 'Out'] as $direction) {
                    foreach (['Octets', 'UcastPkts', 'MulticastPkts', 'BroadcastPkts'] as $counter) {
                        $columns[] = "ifHC$direction$counter";
                    }
                }
            }
        }

        return $columns;
    }

    private function supportedPortColumns(?string $stored): array
    {
        $columns = $this->portMetricColumns();

        return $stored ? array_intersect($columns, explode(',', $stored)) : $columns;
    }

    private function walkPortMetrics(array $ports, array $identity, array $extraOids): array
    {
        $stored = $this->getDevice()->getAttrib('ports_if_mib_columns');
        $columns = $this->supportedPortColumns($stored);
        $config = $this->getDevice()->polling()->snmp();
        if ($stored && (count($ports) === 1 || ($config->version === 'v1' && count($ports) <= $config->maxOid))) {
            $rows = $this->getPortColumns(array_keys($ports), $this->ifMibOids([...$identity, ...$columns]));

            return $extraOids ? array_replace_recursive($rows, $this->portTable($extraOids, true)) : $rows;
        }

        // A device may implement 64-bit counters on only some of its interfaces.
        $fallbacks = [];
        foreach ($columns as $field) {
            if (str_starts_with($field, 'ifHC')) {
                $fallbacks['if' . substr($field, 4)] = $field;
            }
        }
        $fallbacks = array_intersect_key($fallbacks, array_flip($columns));
        $primary = array_diff($columns, array_keys($fallbacks));
        $rows = $this->portTable([...$this->ifMibOids([...$identity, ...$primary]), ...$extraOids], true);
        $fallback = [];
        foreach ($fallbacks as $field => $hc) {
            foreach ($ports as $index => $port) {
                if (! ctype_digit((string) ($rows[$index][$hc] ?? ''))) {
                    $fallback[] = $field;
                    break;
                }
            }
        }

        return $fallback ? array_replace_recursive($rows, $this->portTable($this->ifMibOids($fallback), true)) : $rows;
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
        $fields = array_diff($this->supportedPortColumns($this->getDevice()->getAttrib('ports_if_mib_columns')), ['ifOperStatus', 'ifAdminStatus']);
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

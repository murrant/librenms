<?php

namespace LibreNMS\OS\Traits;

use App\Facades\LibrenmsConfig;
use LibreNMS\Ports\PortMeasurement;

trait CiscoIosPorts
{
    private const CIE_FIELDS = ['InRuntsErrs', 'InGiantsErrs', 'InFramingErrs', 'InOverrunErrs', 'InIgnored', 'InAbortErrs', 'InputQueueDrops', 'OutputQueueDrops'];

    protected function pollIfMibRows(array $knownPorts, bool $selected, array $extraOids): array
    {
        $columns = $selected ? array_map(fn ($field) => 'CISCO-IF-EXTENSION-MIB::cieIf' . $field, self::CIE_FIELDS)
            : ['CISCO-IF-EXTENSION-MIB::cieIfPacketStatsTable'];
        $ports = parent::pollIfMibRows($knownPorts, $selected, [...$extraOids, ...$columns]);
        foreach ($ports as &$port) {
            if (isset($port['cieIfInRuntsErrs'])) {
                foreach (self::CIE_FIELDS as $field) {
                    $port['cie'][$field] = $port['cieIf' . $field] ?? null;
                }
            }
        }

        return $ports && LibrenmsConfig::get('enable_ports_poe') ? $this->withPortPower($ports) : $ports;
    }

    private function withPortPower(array $ports): array
    {
        $supplies = $this->portTable('CISCO-POWER-ETHERNET-EXT-MIB::cpeExtPsePortTable');
        if (! $supplies) {
            return $ports;
        }
        $indexes = \SnmpQuery::walk('CISCO-STACK-MIB::portIfIndex')->throwIfConnectionError()->pluck();
        if (! $indexes) {
            foreach ($ports as $index => $row) {
                if (preg_match('#^[a-z]+ethernet(\d+)/(\d+)(?:/(\d+))?$#i', $row['ifDescr'] ?? '', $match)) {
                    $indexes[$match[1] . '.' . ($match[3] ?? $match[2])] = $index;
                }
            }
        }
        foreach ($supplies as $key => $row) {
            if (isset($indexes[$key], $ports[$indexes[$key]], $row['cpeExtPsePortPwrAllocated'])) {
                $ports[$indexes[$key]]['poe'] = PortMeasurement::powerFields($row['cpeExtPsePortPwrAllocated'], $row['cpeExtPsePortPwrAvailable'] ?? null,
                    $row['cpeExtPsePortPwrConsumption'] ?? null, $row['cpeExtPsePortMaxPwrDrawn'] ?? null);
            }
        }

        return $ports;
    }
}

<?php

namespace LibreNMS\OS\Traits;

trait CiscoIosPorts
{
    private const CIE_FIELDS = ['InRuntsErrs', 'InGiantsErrs', 'InFramingErrs', 'InOverrunErrs', 'InIgnored', 'InAbortErrs', 'InputQueueDrops', 'OutputQueueDrops'];

    protected function portLinkOids(bool $selected): array
    {
        $columns = $selected ? array_map(fn ($field) => 'CISCO-IF-EXTENSION-MIB::cieIf' . $field, self::CIE_FIELDS)
            : ['CISCO-IF-EXTENSION-MIB::cieIfPacketStatsTable'];

        return [...parent::portLinkOids($selected), ...$columns];
    }

    protected function pollPortMetrics(array &$ports): void
    {
        foreach ($ports as &$port) {
            if (isset($port['cieIfInRuntsErrs'])) {
                foreach (self::CIE_FIELDS as $field) {
                    $port['cie'][$field] = $port['cieIf' . $field] ?? null;
                }
            }
        }
    }

    protected function collectPortPower(array $ports): array
    {
        $supplies = $this->portTable('CISCO-POWER-ETHERNET-EXT-MIB::cpeExtPsePortTable');
        if (! $supplies) {
            return [];
        }
        $indexes = \SnmpQuery::walk('CISCO-STACK-MIB::portIfIndex')->throwIfConnectionError()->pluck();
        if (! $indexes) {
            foreach ($ports as $index => $row) {
                if (preg_match('#^[a-z]+ethernet(\d+)/(\d+)(?:/(\d+))?$#i', $row['ifDescr'] ?? '', $match)) {
                    $indexes[$match[1] . '.' . ($match[3] ?? $match[2])] = $index;
                }
            }
        }
        $result = [];
        foreach ($supplies as $key => $row) {
            if (isset($indexes[$key], $row['cpeExtPsePortPwrAllocated'])) {
                $result[$indexes[$key]] = $this->portPowerFields($row['cpeExtPsePortPwrAllocated'], $row['cpeExtPsePortPwrAvailable'] ?? null,
                    $row['cpeExtPsePortPwrConsumption'] ?? null, $row['cpeExtPsePortMaxPwrDrawn'] ?? null);
            }
        }

        return $result;
    }
}

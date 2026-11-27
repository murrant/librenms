<?php

namespace LibreNMS\OS;

class Wavence extends Generic
{
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = parent::collectPortRows($knownPorts, $selected);
        foreach ($ports as &$port) {
            foreach (['ifName', 'ifAlias'] as $field) {
                if (($port[$field] ?? '') === 'NULL') {
                    unset($port[$field]);
                }
            }
        }
        unset($port);
        if ($knownPorts === null) {
            return $ports;
        }

        $map = ['ifHCInOctets' => 'ethAggrMaintRxTRCO', 'ifHCOutOctets' => 'ethAggrMaintTxTTO',
            'ifInErrors' => 'ethAggrMaintRxTRSEF', 'ifInDiscards' => 'ethAggrMaintRxTDF', 'ifOutDiscards' => 'ethAggrMaintTxTDF'];
        $counters = $this->portTable(array_map(fn ($oid) => "OPTICSIM-ETHPM-MIB::$oid", $map));
        foreach ($ports as $index => &$port) {
            if (($port['ifType'] ?? '') === 'ethernetCsmacd') {
                $port = $this->mapPortFields($counters[$index] ?? [], $map) + $port;
            }
        }

        return $ports;
    }
}

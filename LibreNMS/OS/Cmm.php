<?php

namespace LibreNMS\OS;

class Cmm extends Generic
{
    // The controller reports zero for HC counters while its 32-bit counters advance.
    protected function portMetricColumns(): array
    {
        return array_filter(parent::portMetricColumns(), fn ($field) => ! str_starts_with($field, 'ifHC'));
    }

    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = parent::collectPortRows($knownPorts, $selected);
        $rows = $this->portTable([$knownPorts === null ? 'CMM3-MIB::portNumber' : 'CMM3-MIB::cmmSwitchTable', 'CMM3-MIB::cmmPortTable']);
        foreach (array_values($rows) as $index => $row) {
            if (! isset($row['portNumber'])) {
                continue;
            }
            $name = 'CMM Port ' . $row['portNumber'];
            $ports[$index] = ($ports[$index] ?? []) + $this->mapPortFields($row, [
                'ifInOctets' => 'rxOctets',
                'ifOutOctets' => 'txOctets',
                'ifInUcastPkts' => 'rxUnicastPkts',
                'ifOutUcastPkts' => 'txUnicastPkts',
                'ifInErrors' => 'rxDropPkts',
                'ifOutErrors' => 'txDropPkts',
                'ifInBroadcastPkts' => 'rxBroadcastPkts',
                'ifOutBroadcastPkts' => 'txBroadcastPkts',
                'ifInMulticastPkts' => 'rxMulticastPkts',
                'ifOutMulticastPkts' => 'txMulticastPkts',
            ]) + [
                'ifDescr' => $name,
                'ifName' => $name,
                'ifType' => 'ethernetCsmacd',
                'ifDuplex' => ($row['duplexStatus'] ?? 0) == 1 ? 'fullDuplex' : 'halfDuplex',
                'ifSpeed' => ($row['linkSpeed'] ?? 0) == 1 ? 100000000 : 10000000,
                'ifOperStatus' => ($row['linkStatus'] ?? 0) == 1 ? 'up' : 'down',
            ];
        }

        return $ports;
    }
}

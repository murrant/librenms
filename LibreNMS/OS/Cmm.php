<?php

namespace LibreNMS\OS;

class Cmm extends Generic
{
    protected function addVendorPorts(array &$ports): void
    {
        $rows = $this->portTable(['CMM3-MIB::cmmSwitchTable', 'CMM3-MIB::cmmPortTable']);
        // The controller advertises HC columns that stay at zero while the 32-bit counters advance.
        foreach ($ports as &$controllerPort) {
            $controllerPort = array_filter($controllerPort, fn ($key) => ! str_starts_with($key, 'ifHC'), ARRAY_FILTER_USE_KEY);
        }
        unset($controllerPort);
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
    }
}

<?php

namespace LibreNMS\OS;

class MlosNsp extends Generic
{
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = parent::collectPortRows($knownPorts, $selected);
        $rows = $this->portTable($knownPorts === null ? 'TRELLIX-SENSOR-CONF-MIB::intfPortTable' : ['TRELLIX-SENSOR-CONF-MIB::intfPortTable', 'TRELLIX-SENSOR-PERF-MIB::intfPortIf64Table']);
        $speeds = [1 => 10000000, 100000000, 1000000000, 10000000000, 40000000000];
        foreach (array_values($rows) as $index => $row) {
            if (! isset($row['intfPortIfDescr'])) {
                continue;
            }
            $ports[$index] = ($ports[$index] ?? []) + $this->mapPortFields($row, [
                'ifInOctets' => 'intfPortTotalBytesRecv64',
                'ifOutOctets' => 'intfPortTotalBytesSent64',
                'ifInUcastPkts' => 'intfPortTotalUnicastPktsRecv64',
                'ifOutUcastPkts' => 'intfPortTotalUnicastPktsSent64',
                'ifInErrors' => 'intfPortTotalCRCErrorsRecv64',
                'ifOutErrors' => 'intfPortTotalCRCErrorsSent64',
            ]) + [
                'ifDescr' => $row['intfPortIfDescr'],
                'ifName' => $row['intfPortIfDescr'],
                'ifType' => 'ethernetCsmacd',
                'ifSpeed' => $speeds[$row['intfPortSpeed'] ?? 0] ?? null,
                'ifDuplex' => ($row['intfPortEnableFullDuplex'] ?? 0) == 1 ? 'fullDuplex' : 'halfDuplex',
                'ifOperStatus' => ($row['intfPortIfOperStatus'] ?? 0) == 1 ? 'up' : 'down',
                'ifAdminStatus' => ($row['intfPortIfAdminStatus'] ?? 0) == 1 ? 'up' : 'down',
            ];
        }

        return $ports;
    }
}

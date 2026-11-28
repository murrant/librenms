<?php

namespace LibreNMS\OS;

class ExalinkFusion extends Generic
{
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = [];
        foreach ($this->portTable('EXALINK-FUSION-MIB::fusionPortTable', true) as $key => $row) {
            $parts = explode('.', trim($key, '"'));
            if (count($parts) !== 2 || ! isset($row['fusionPortName'])) {
                continue;
            }
            $ports[((int) $parts[0] - 1) * 16 + (int) $parts[1]] = [
                'ifName' => $row['fusionPortName'],
                'ifDescr' => $row['fusionPortName'],
                'ifType' => 'ethernetCsmacd',
                'ifOperStatus' => ($row['fusionPortHasSignal'] ?? '') === 'true' ? 'up' : 'down',
                'ifAdminStatus' => ($row['fusionPortEnabled'] ?? '') === 'true' ? 'up' : 'down',
            ] + $this->mapPortFields($row, [
                'ifAlias' => 'fusionPortAlias',
                'ifHighSpeed' => 'fusionPortSpeed',
                'ifHCInOctets' => 'fusionPortRXBytes',
                'ifHCOutOctets' => 'fusionPortTXBytes',
                'ifInErrors' => 'fusionPortRXErrors',
                'ifConnectorPresent' => 'fusionPortPresent',
            ]);
        }

        return $ports;
    }
}

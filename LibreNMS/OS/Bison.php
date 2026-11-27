<?php

namespace LibreNMS\OS;

class Bison extends Generic
{
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = [];
        $columns = $knownPorts === null ? ['vifCvid'] : ['vifRxOctets', 'vifTxOctets', 'vifRxPkts', 'vifTxPkts'];
        if ($knownPorts === null || $this->portAssociationMode() !== 'ifIndex') {
            array_push($columns, 'vifName', 'vifPort');
        }
        foreach ($this->portTable(array_map(fn ($column) => "BISON-ROUTER-MIB::$column", $columns)) as $index => $row) {
            $ports[1000 + $index] = $this->mapPortFields($row, [
                'ifDescr' => 'vifName',
                'ifName' => 'vifPort',
                'ifInOctets' => 'vifRxOctets',
                'ifOutOctets' => 'vifTxOctets',
                'ifInUcastPkts' => 'vifRxPkts',
                'ifOutUcastPkts' => 'vifTxPkts',
                'ifVlan' => 'vifCvid',
            ]) + ['ifAdminStatus' => 'up', 'ifOperStatus' => 'up'];
        }

        return $ports;
    }
}

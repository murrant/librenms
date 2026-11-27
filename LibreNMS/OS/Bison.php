<?php

namespace LibreNMS\OS;

class Bison extends Generic
{
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = [];
        foreach ($this->portTable('BISON-ROUTER-MIB::vifTable') as $index => $row) {
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

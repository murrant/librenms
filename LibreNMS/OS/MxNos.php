<?php

namespace LibreNMS\OS;

class MxNos extends Generic
{
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = parent::collectPortRows($knownPorts, $selected);
        $oids = [];
        if ($knownPorts !== null) {
            $oids[] = 'MOXA-TCST-MIB::tcstStatGroupTable';
        }
        if ($knownPorts === null || $this->portAssociationMode() === 'ifAlias') {
            $oids[] = 'MOXA-PORT-MIB::portConfigDescription';
        }
        foreach ($this->portTable($oids) as $index => $row) {
            $ports[$index] = $this->mapPortFields($row, [
                'ifInOctets' => 'tcstStatRxTotalOctets',
                'ifInUcastPkts' => 'tcstStatRxUnicastPackets',
                'ifInNUcastPkts' => 'tcstStatRxMulticastPackets',
                'ifHCInBroadcastPkts' => 'tcstStatRxBroadcastPackets',
                'ifInErrors' => 'tcstStatRxErrorsPackets',
                'ifInDiscards' => 'tcstStatRxUnknownProtosPackets',
                'ifOutOctets' => 'tcstStatTxTotalOctets',
                'ifOutUcastPkts' => 'tcstStatTxUnicastPackets',
                'ifOutNUcastPkts' => 'tcstStatTxMulticastPackets',
                'ifHCOutBroadcastPkts' => 'tcstStatTxBroadcastPackets',
                'ifOutErrors' => 'tcstStatTxErrorsPackets',
                'ifOutDiscards' => 'tcstStatTxDiscardsPackets',
                'ifAlias' => 'portConfigDescription',
            ]) + ($ports[$index] ?? []);
        }

        return $ports;
    }
}

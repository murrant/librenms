<?php

namespace LibreNMS\OS;

class MxNos extends Generic
{
    protected function addVendorPorts(array &$ports): void
    {
        foreach ($this->portTable(['MOXA-TCST-MIB::tcstStatGroupTable', 'MOXA-PORT-MIB::portConfigDescription']) as $index => $row) {
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
    }
}

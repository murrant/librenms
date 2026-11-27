<?php

namespace LibreNMS\OS;

class Procera extends Shared\Unix
{
    /**
     * @author     Paul Heinrichs <pdheinrichs@gmail.com>
     * @copyright  2017 Paul Heinrichs
     */
    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = parent::collectPortRows($knownPorts, $selected);
        $oids = ['PACKETLOGIC-CHANNEL-MIB::channelName', 'PACKETLOGIC-CHANNEL-MIB::channelActive', 'PACKETLOGIC-CHANNEL-MIB::channelInternalNegotiatedMedia'];
        if ($knownPorts !== null) {
            $oids[] = 'PACKETLOGIC-CHANNEL-MIB::netDeviceTable';
        }
        foreach ($this->portTable($oids) as $key => $row) {
            if (! isset($row['channelName'])) {
                continue;
            }
            $index = explode('.', $key)[0];
            $mode = (int) ($row['channelInternalNegotiatedMedia'] ?? 0);
            $name = $row['channelName'] . ' Internal';
            $ports[$index] = [
                'ifName' => $name,
                'ifDescr' => $name,
                'ifType' => 'ethernetCsmacd',
                'ifConnectorPresent' => $mode ? 'true' : 'false',
                'ifOperStatus' => ($row['channelActive'] ?? 0) == 1 ? 'up' : 'down',
                'ifDuplex' => match ($mode) {
                    0 => null,
                    1, 3 => 'halfDuplex',
                    default => 'fullDuplex',
                },
                'ifSpeed' => [0, 10000000, 10000000, 100000000, 100000000, 1000000000, 10000000000][$mode] ?? null,
            ] + $this->mapPortFields($row, [
                'ifInOctets' => 'channelInternalRxBytes',
                'ifOutOctets' => 'channelInternalTxBytes',
                'ifInUcastPkts' => 'channelInternalRxPackets',
                'ifOutUcastPkts' => 'channelInternalTxPackets',
                'ifInErrors' => 'channelInternalRxErrors',
                'ifOutErrors' => 'channelInternalTxErrors',
            ]);
        }

        return $ports;
    }
}

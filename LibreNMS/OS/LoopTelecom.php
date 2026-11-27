<?php

namespace LibreNMS\OS;

class LoopTelecom extends Generic
{
    protected const bool USE_IF_MIB = false;

    protected function addVendorPorts(array &$ports): void
    {
        $traffic = [];
        foreach ($this->portTable('L-AM3440-A-Private::ethernetCountTable') as $row) {
            $traffic[$row['ethernetCountName'] ?? ''] = $row;
        }
        foreach ($this->portTable('L-AM3440-A-Private::ethernetStatusTable') as $row) {
            if (! isset($row['ethernetStatusName'])) {
                continue;
            }
            $name = \LibreNMS\Util\StringHelpers::decodeSnmpHexText($row['ethernetStatusName']);
            $mode = (int) ($row['ethernetStatusSpeed'] ?? 0);
            $link = ($row['ethernetStatusLink'] ?? 0) == 1 ? 'up' : 'down';
            $ports[count($ports) + 1] = [
                'ifName' => $name,
                'ifDescr' => $name,
                'ifType' => 'ethernetCsmacd',
                'ifOperStatus' => $link,
                'ifAdminStatus' => $link,
                'ifSpeed' => match ($mode) {
                    1, 2, 3 => 1_000_000_000,
                    4, 5 => 100_000_000,
                    6, 7 => 10_000_000,
                    default => null,
                },
                'ifDuplex' => match ($mode) {
                    1, 2, 4, 6 => 'fullDuplex',
                    3, 5, 7 => 'halfDuplex',
                    default => null,
                },
            ] + array_map(abs(...), $this->mapPortFields($traffic[$row['ethernetStatusName']] ?? [], [
                'ifInOctets' => 'ethernetRxGoodPkt',
                'ifOutOctets' => 'ethernetTxGoodPkt',
                'ifInErrors' => 'ethernetRxBadCount',
            ]));
        }
    }
}

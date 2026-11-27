<?php

namespace LibreNMS\OS;

class CxrTs extends Generic
{
    /**
     * @author     PipoCanaja <PipoCanaja@gmail.com>
     * @copyright  PipoCanaja 2018
     */
    protected function addVendorPorts(array &$ports): void
    {
        foreach ($this->portTable(['CXR-TS-MIB::portTable', 'CXR-TS-MIB::portStatsTable'], true) as $index => $row) {
            $alias = "Port $index, " . implode(', ', [$row['terminalType'] ?? '', $row['mode'] ?? '', trim(($row['baudRate'] ?? '') . ' ' . ($row['nbParStop'] ?? ''))]);
            foreach (['Alias' => ['aliasIpAddress', 'tcpPort'], 'Remote' => ['remoteIpAddress', 'remoteTcpPort']] as $label => [$ip, $tcp]) {
                if (isset($row[$ip], $row[$tcp]) && $row[$ip] !== '0.0.0.0') {
                    $alias .= ", $label IP: " . $row[$ip] . ':' . $row[$tcp];
                }
            }
            $ports[1000 + $index] = [
                'ifName' => "Serial$index",
                'ifDescr' => "SerialPort$index",
                'ifType' => 'rs232',
                'ifAlias' => $alias,
                'ifOperStatus' => 'up',
                'ifAdminStatus' => 'up',
                'ifSpeed' => preg_replace('/[^0-9.]/', '', $row['baudRate'] ?? '')]
                + $this->mapPortFields($row, ['ifInOctets' => 'bytesReceiveFromV24', 'ifOutOctets' => 'bytesSendToV24']);
        }
    }
}

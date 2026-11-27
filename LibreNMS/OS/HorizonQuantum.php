<?php

namespace LibreNMS\OS;

use LibreNMS\Device\WirelessSensor;
use LibreNMS\Enum\WirelessSensorType;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessErrorsDiscovery;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessPowerDiscovery;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessRateDiscovery;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessRssiDiscovery;
use LibreNMS\Interfaces\Discovery\Sensors\WirelessSnrDiscovery;
use LibreNMS\OS;

class HorizonQuantum extends OS implements
    WirelessSnrDiscovery,
    WirelessPowerDiscovery,
    WirelessRssiDiscovery,
    WirelessErrorsDiscovery,
    WirelessRateDiscovery
{
    public function discoverWirelessSnr()
    {
        $index = snmpwalk_group($this->getDeviceArray(), 'hzQtmModemIndex', 'DRAGONWAVE-HORIZON-QUANTUM-MIB');
        $data = snmpwalk_group($this->getDeviceArray(), 'hzQtmModemSNR', 'DRAGONWAVE-HORIZON-QUANTUM-MIB');
        $sensors = [];
        foreach ($data as $oid => $snr_value) {
            if ($snr_value['hzQtmModemSNR'] != '-99') {
                $sensors[] = new WirelessSensor(
                    WirelessSensorType::Snr,
                    $this->getDeviceId(),
                    '.1.3.6.1.4.1.7262.2.4.5.2.1.1.8.' . $index[$oid]['hzQtmModemIndex'],
                    'horizon-quantum',
                    $oid,
                    $oid . ' SNR',
                    null,
                    1,
                    10
                );
            }
        }

        return $sensors;
    }

    public function discoverWirelessPower()
    {
        $index = snmpwalk_group($this->getDeviceArray(), 'hzQtmRadioIndex', 'DRAGONWAVE-HORIZON-QUANTUM-MIB');
        $data = snmpwalk_group($this->getDeviceArray(), 'hzQtmRadioActualTransmitPowerdBm', 'DRAGONWAVE-HORIZON-QUANTUM-MIB');
        $sensors = [];
        foreach ($data as $oid => $power_value) {
            if ($power_value['hzQtmRadioActualTransmitPowerdBm'] != '-99') {
                $sensors[] = new WirelessSensor(
                    WirelessSensorType::Power,
                    $this->getDeviceId(),
                    '.1.3.6.1.4.1.7262.2.4.5.4.1.1.19.' . $index[$oid]['hzQtmRadioIndex'],
                    'horizon-quantum-tx',
                    $oid,
                    $oid . ' TX Power',
                    null,
                    1,
                    10
                );
            }
        }

        return $sensors;
    }

    public function discoverWirelessRssi()
    {
        $index = snmpwalk_group($this->getDeviceArray(), 'hzQtmModemIndex', 'DRAGONWAVE-HORIZON-QUANTUM-MIB');
        $data = snmpwalk_group($this->getDeviceArray(), 'hzQtmModemChannelizedRSL', 'DRAGONWAVE-HORIZON-QUANTUM-MIB');
        $sensors = [];
        foreach ($data as $oid => $rssi_value) {
            if ($rssi_value['hzQtmModemChannelizedRSL'] != '-99') {
                $sensors[] = new WirelessSensor(
                    WirelessSensorType::Rssi,
                    $this->getDeviceId(),
                    '.1.3.6.1.4.1.7262.2.4.5.2.1.1.3.' . $index[$oid]['hzQtmModemIndex'],
                    'horizon-quantum',
                    $oid,
                    $oid . ' RSSI',
                    null,
                    1,
                    10
                );
            }
        }

        return $sensors;
    }

    public function discoverWirelessErrors()
    {
        $index = snmpwalk_group($this->getDeviceArray(), 'hzQtmWirelessEnetPortIndex', 'DRAGONWAVE-HORIZON-QUANTUM-MIB');
        $data = snmpwalk_group($this->getDeviceArray(), 'hzQtmWirelessEnetPortRxFramesErrors', 'DRAGONWAVE-HORIZON-QUANTUM-MIB');
        $sensors = [];
        foreach ($data as $oid => $errors_value) {
            if ($errors_value['hzQtmWirelessEnetPortRxFramesErrors'] != '-99') {
                $sensors[] = new WirelessSensor(
                    WirelessSensorType::Errors,
                    $this->getDeviceId(),
                    '.1.3.6.1.4.1.7262.2.4.5.2.3.1.4.' . $index[$oid]['hzQtmWirelessEnetPortIndex'],
                    'horizon-quantum-rx',
                    $oid,
                    $oid . ' Rx Errors'
                );
            }
        }

        return $sensors;
    }

    public function discoverWirelessRate()
    {
        $sensors = [];
        $index = snmpwalk_group($this->getDeviceArray(), 'hzQtmModemIndex', 'DRAGONWAVE-HORIZON-QUANTUM-MIB');

        $data = snmpwalk_group($this->getDeviceArray(), 'hzQtmModemRxSpeed', 'DRAGONWAVE-HORIZON-QUANTUM-MIB');
        foreach ($data as $oid => $rate_value) {
            if ($rate_value['hzQtmModemRxSpeed'] != '-99') {
                $sensors[] = new WirelessSensor(
                    WirelessSensorType::Rate,
                    $this->getDeviceId(),
                    '.1.3.6.1.4.1.7262.2.4.5.2.1.1.6.' . $index[$oid]['hzQtmModemIndex'],
                    'horizon-quantum-rx',
                    $oid,
                    $oid . ' Rx Rate',
                    null,
                    1,
                    10
                );
            }
        }

        $data = snmpwalk_group($this->getDeviceArray(), 'hzQtmModemTxSpeed', 'DRAGONWAVE-HORIZON-QUANTUM-MIB');
        foreach ($data as $oid => $rate_value) {
            if ($rate_value['hzQtmModemTxSpeed'] != '-99') {
                $sensors[] = new WirelessSensor(
                    WirelessSensorType::Rate,
                    $this->getDeviceId(),
                    '.1.3.6.1.4.1.7262.2.4.5.2.1.1.7.' . $index[$oid]['hzQtmModemIndex'],
                    'horizon-quantum-tx',
                    $oid,
                    $oid . ' Tx Rate',
                    null,
                    1,
                    10
                );
            }
        }

        return $sensors;
    }

    protected function collectPortRows(?array $knownPorts, bool $selected): array
    {
        $ports = parent::collectPortRows($knownPorts, $selected);
        $oids = ['DRAGONWAVE-HORIZON-QUANTUM-MIB::hzQtmEnetPortConfigTable', 'DRAGONWAVE-HORIZON-QUANTUM-MIB::hzQtmEnetPortStatusTable'];
        if ($knownPorts !== null) {
            $oids[] = 'DRAGONWAVE-HORIZON-QUANTUM-MIB::hzQtmEnetPortStatsTable';
        }
        $rows = $this->portTable($oids);
        foreach ($rows as $row) {
            if (! isset($row['hzQtmEnetPortIndex'])) {
                continue;
            }
            $index = $row['hzQtmEnetPortIndex'];
            $ports[$index] = $this->mapPortFields($row, [
                'ifName' => 'hzQtmEnetPortName',
                'ifDescr' => 'hzQtmEnetPortName',
                'ifMtu' => 'hzQtmEnetPortMaxFrameSize',
                'ifInOctets' => 'hzQtmEnetPortRxBytes',
                'ifOutOctets' => 'hzQtmEnetPortTxBytes',
                'ifInUcastPkts' => 'hzQtmEnetPortRxUcastPkts',
                'ifOutUcastPkts' => 'hzQtmEnetPortTxUcastPkts',
                'ifInNUcastPkts' => 'hzQtmEnetPortRxNUcastPkts',
                'ifOutNUcastPkts' => 'hzQtmEnetPortTxNUcastPkts',
                'ifInErrors' => 'hzQtmEnetPortRxErrors',
                'ifOutErrors' => 'hzQtmEnetPortTxErrors',
                'ifInDiscards' => 'hzQtmEnetPortRxDiscards',
                'ifOutDiscards' => 'hzQtmEnetPortTxDiscards',
                'ifInUnknownProtos' => 'hzQtmEnetPortRxUnknownProtos',
            ]) + [
                'ifOperStatus' => ($row['hzQtmEnetPortLinkStatus'] ?? 0) == 2 ? 'up' : 'down',
                'ifAdminStatus' => ($row['hzQtmEnetPortAdminState'] ?? 0) == 1 ? 'up' : 'down',
                'ifHighSpeed' => [1 => 10, 100, 1000, 1000][$row['hzQtmEnetPortSpeed'] ?? 0] ?? null,
            ] + ($ports[$index] ?? []);
        }

        return $ports;
    }
}

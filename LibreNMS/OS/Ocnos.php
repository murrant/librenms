<?php
/*
 * Ocnos.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2024 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\OS;

use App\Models\Transceiver;
use App\Models\TransceiverMetric;
use Illuminate\Support\Collection;
use LibreNMS\Interfaces\Discovery\TransceiverDiscovery;
use LibreNMS\OS;
use SnmpQuery;

class Ocnos extends OS implements TransceiverDiscovery
{

    public function discoverTransceivers(): Collection
    {
        return SnmpQuery::enumStrings()->walk('IPI-CMM-CHASSIS-MIB::cmmTransEEPROMTable')->mapTable(function ($data, $cmmStackUnitIndex, $cmmTransIndex) {
            $distance = 0;
            if (! empty($data['IPI-CMM-CHASSIS-MIB::cmmTransLengthMtrs']) && $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthMtrs'] !== '-100002') {
                $distance = (int) $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthMtrs'];
            } elseif (! empty($data['IPI-CMM-CHASSIS-MIB::cmmTransLengthKmtrs']) && $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthKmtrs'] !== '-100002') {
                $distance = $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthKmtrs'] * 1000;
            } elseif (! empty($data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM4']) && $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM4'] !== '-100002') {
                $distance = (int) $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM4'];
            } elseif (! empty($data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM3'] )&& $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM3'] !== '-100002') {
                $distance = (int) $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM3'];
            } elseif (! empty($data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM2']) && $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM2'] !== '-100002') {
                $distance = (int) $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM2'];
            } elseif (! empty($data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM1']) && $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM1'] !== '-100002') {
                $distance = (int) $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM1'];
            }

            $connector = match ($data['IPI-CMM-CHASSIS-MIB::cmmTransconnectortype'] ?? null) {
                'bayonet-or-threaded-neill-concelman' => 'ST',
                'copper-pigtail' => 'DAC',
                'fiber-jack' => 'FJ',
                'fibrechannel-style1-copperconnector', 'fibrechannel-style2-copperconnector', 'fibrechannel-coaxheaders' => 'FC',
                'hssdcii' => 'HSSDC',
                'lucent-connector' => 'LC',
                'mechanical-transfer-registeredjack' => 'MTRJ',
                'multifiber-paralleloptic-1x12' => 'MPO-12',
                'multifiber-paralleloptic-1x16' => 'MPO-16',
                'multiple-optical' => 'MPO',
                'mxc2-x16' => 'MXC2-X16',
                'no-separable-connector' => 'None',
                'optical-pigtail' => 'AOC',
                'rj45' => 'RJ45',
                'sg' => 'SG',
                'subscriber-connector' => 'SC',
                default => 'unknown',
            };

            $date = $data['IPI-CMM-CHASSIS-MIB::cmmTransDateCode'] ?? '0000-00-00';
            if (preg_match('/^(\d{2,4})(\d{2})(\d{2})$/', $date, $date_matches)) {
                $year = $date_matches[1];
                if (strlen($year) == 2) {
                    $year = '20' . $year;
                }
                $date = $year . '-' . $date_matches[2] . '-' . $date_matches[3];
            }

            return new Transceiver([
                'port_id' => 0,
                'index' => "$cmmStackUnitIndex.$cmmTransIndex",
                'type' => $data['IPI-CMM-CHASSIS-MIB::cmmTransType'] ?? 'missing',
                'vendor' => $data['IPI-CMM-CHASSIS-MIB::cmmTransVendorName'] ?? 'missing',
                'oui' => $data['IPI-CMM-CHASSIS-MIB::cmmTransVendorOUI'] ?? 'missing',
                'model' => $data['IPI-CMM-CHASSIS-MIB::cmmTransVendorPartNumber'] ?? 'missing',
                'revision' => $data['IPI-CMM-CHASSIS-MIB::cmmTransVendorRevision'] ?? 'missing',
                'serial' => $data['IPI-CMM-CHASSIS-MIB::cmmTransVendorSerialNumber'] ?? 'missing',
                'date' => $date,
                'ddm' => isset($data['IPI-CMM-CHASSIS-MIB::cmmTransDDMSupport']) && $data['IPI-CMM-CHASSIS-MIB::cmmTransDDMSupport'] == 'yes',
                'encoding' => $data['IPI-CMM-CHASSIS-MIB::cmmTransEncoding'] ?? 'missing',
                'distance' => $distance,
                'connector' => $connector,
                'channels' => $data['IPI-CMM-CHASSIS-MIB::cmmTransNoOfChannels'] ?? 'missing',
            ]);
        });
    }

    public function discoverTransceiverMetrics(Collection $transceivers): Collection
    {
        $metric_data = \SnmpQuery::enumStrings()->walk('IPI-CMM-CHASSIS-MIB::cmmTransDDMTable')->table(3);
        $metrics = new Collection;

        foreach ($metric_data as $chassis => $chassis_data) {
            foreach ($chassis_data as $module => $module_data) {
                // get the transceiver and start a new metric collection
                $transceiver = $transceivers->get("$chassis.$module");

                if (! $transceiver) {
                    continue; // data for un-discovered transceiver
                }

                foreach ($module_data as $channel => $channel_data) {
                    // temp
                    if (isset($channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTemperature']) && $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTemperature'] != '-100001') {
                        $metrics->push($this->getTransceiverMetric($transceiver, 2, $chassis, $module, $channel, $channel_data, 'voltage', 'TransTemperature', 'TransTemp'));
                    }

                    // voltage
                    if (isset($channel_data['IPI-CMM-CHASSIS-MIB::cmmTransVoltage']) && $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransVoltage'] != '-100001') {
                        $metrics->push($this->getTransceiverMetric($transceiver, 7, $chassis, $module, $channel, $channel_data, 'voltage', 'TransVoltage', 'TransVolt'));
                    }

                    // bias
                    if (isset($channel_data['IPI-CMM-CHASSIS-MIB::cmmTransLaserBiasCurrent']) && $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransLaserBiasCurrent'] != '-100001') {
                        $metrics->push($this->getTransceiverMetric($transceiver, 12, $chassis, $module, $channel, $channel_data, 'bias', 'TransLaserBiasCurrent', 'TransLaserBiasCurr'));
                    }

                    // power-tx
                    if (isset($channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTxPowerSupported']) && $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTxPowerSupported'] == 'supported') {
                        $metrics->push($this->getTransceiverMetric($transceiver, 17, $chassis, $module, $channel, $channel_data, 'power-tx', 'TransTxPower'));
                    }

                    // power-rx
                    if (isset($channel_data['IPI-CMM-CHASSIS-MIB::cmmTransRxPowerSupported']) && $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransRxPowerSupported'] == 'supported') {
                        $metrics->push($this->getTransceiverMetric($transceiver, 22, $chassis, $module, $channel, $channel_data, 'power-rx', 'TransRxPower'));
                    }
                }
            }
        }

        return $metrics;
    }


    private function getTransceiverMetric(Transceiver $transceiver, int $snmp_field_index, string $chassis, string $module, string $channel, array $data, string $type, string $slug, string $threshold_slug = null): TransceiverMetric
    {
        $divisor = 100;
        $threshold_slug ??= $slug;

        // be safe against missing data
        $value = $data["IPI-CMM-CHASSIS-MIB::cmm{$slug}"] ?? null;
        $min_crit = $data["IPI-CMM-CHASSIS-MIB::cmm{$threshold_slug}CriticalThresholdMin"] ?? null;
        $min_warn = $data["IPI-CMM-CHASSIS-MIB::cmm{$threshold_slug}AlertThresholdMin"] ?? null;
        $max_warn = $data["IPI-CMM-CHASSIS-MIB::cmm{$threshold_slug}AlertThresholdMax"] ?? null;
        $max_crit = $data["IPI-CMM-CHASSIS-MIB::cmm{$threshold_slug}CriticalThresholdMax"] ?? null;

        return new TransceiverMetric([
            'transceiver_id' => $transceiver->id,
            'channel' => $channel,
            'description' => "$chassis/$module.$channel",
            'type' => $type,
            'oid' => ".1.3.6.1.4.1.36673.100.1.2.3.1.$snmp_field_index.$chassis.$module.$channel",
            'value' => $value ? $value / $divisor : null,
            'divisor' => $divisor,
            'threshold_min_critical' => $min_crit ? $min_crit / $divisor : null,
            'threshold_min_warning' => $min_warn ? $min_warn / $divisor : null,
            'threshold_max_warning' => $max_warn ? $max_warn / $divisor : null,
            'threshold_max_critical' => $max_crit ? $max_crit / $divisor : null,
        ]);
    }
}
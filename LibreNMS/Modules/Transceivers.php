<?php
/*
 * Transceivers.php
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

namespace LibreNMS\Modules;

use App\Models\Device;
use App\Models\Transceiver;
use App\Models\TransceiverMetric;
use App\Observers\ModuleModelObserver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use LibreNMS\DB\SyncsModels;
use LibreNMS\Interfaces\Data\DataStorageInterface;
use LibreNMS\Interfaces\Module;
use LibreNMS\OS;
use LibreNMS\Polling\ModuleStatus;
use LibreNMS\RRD\RrdDefinition;

class Transceivers implements Module
{
    use SyncsModels;

    public function dependencies(): array
    {
        return ['ports'];
    }

    public function shouldDiscover(OS $os, ModuleStatus $status): bool
    {
        return $status->isEnabledAndDeviceUp($os->getDevice());
    }

    public function shouldPoll(OS $os, ModuleStatus $status): bool
    {
        return $status->isEnabledAndDeviceUp($os->getDevice()) && $os->getDevice()->transceiverMetrics()->exists();
    }

    public function discover(OS $os): void
    {
        echo "\nTransceivers: ";
        ModuleModelObserver::observe(\App\Models\Transceiver::class);

        $transceivers = \SnmpQuery::enumStrings()->walk('IPI-CMM-CHASSIS-MIB::cmmTransEEPROMTable')->mapTable(function ($data, $cmmStackUnitIndex, $cmmTransIndex) {
            $index = implode('.', array_slice(func_get_args(), 1));

            $distance = 0;
            if ($data['IPI-CMM-CHASSIS-MIB::cmmTransLengthMtrs'] !== '0' && $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthMtrs'] !== '-100002') {
                $distance = (int)$data['IPI-CMM-CHASSIS-MIB::cmmTransLengthMtrs'];
            } elseif ($data['IPI-CMM-CHASSIS-MIB::cmmTransLengthKmtrs'] !== '0' && $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthKmtrs'] !== '-100002') {
                $distance = $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthKmtrs'] * 1000;
            } elseif ($data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM4'] !== '0' && $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM4'] !== '-100002') {
                $distance = (int)$data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM4'];
            } elseif ($data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM3'] !== '0' && $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM3'] !== '-100002') {
                $distance = (int)$data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM3'];
            } elseif ($data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM2'] !== '0' && $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM2'] !== '-100002') {
                $distance = (int)$data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM2'];
            } elseif ($data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM1'] !== '0' && $data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM1'] !== '-100002') {
                $distance = (int)$data['IPI-CMM-CHASSIS-MIB::cmmTransLengthOM1'];
            }

            $connector = match ($data['IPI-CMM-CHASSIS-MIB::cmmTransconnectortype']) {
                'lucent-connector' => 'lc',
                'subscriber-connector' => 'sc',
                'bayonet-or-threaded-neill-concelman' => 'st',
                'multifiber-paralleloptic-1x12' => 'mpo-12',
                'multifiber-paralleloptic-1x16' => 'mpo-16',
                'copper-pigtail' => 'dac',
                'rj45' => 'rj45',
                'fiber-jack' => 'fj',
                default => 'unknown',
            };

            $date = $data['IPI-CMM-CHASSIS-MIB::cmmTransDateCode'];
            if (preg_match('/^(\d{2,4})(\d{2})(\d{2})$/', $date, $date_matches)) {
                $year = $date_matches[1];
                if (strlen($year) == 2) {
                    $year = '20' . $year;
                }
                $date = $year . '-' . $date_matches[2] . '-' . $date_matches[3];
            }

            return new Transceiver([
                'port_id' => 0,
                'index' => $index,
                'type' => $data['IPI-CMM-CHASSIS-MIB::cmmTransType'],
                'vendor' => $data['IPI-CMM-CHASSIS-MIB::cmmTransVendorName'],
                'oui' => $data['IPI-CMM-CHASSIS-MIB::cmmTransVendorOUI'],
                'model' => $data['IPI-CMM-CHASSIS-MIB::cmmTransVendorPartNumber'],
                'revision' => $data['IPI-CMM-CHASSIS-MIB::cmmTransVendorRevision'],
                'serial' => $data['IPI-CMM-CHASSIS-MIB::cmmTransVendorSerialNumber'],
                'date' => $date,
                'ddm' => $data['IPI-CMM-CHASSIS-MIB::cmmTransDDMSupport'] == 'yes',
                'encoding' => $data['IPI-CMM-CHASSIS-MIB::cmmTransEncoding'],
                'distance' => $distance,
                'connector' => $connector,
                'channels' => $data['IPI-CMM-CHASSIS-MIB::cmmTransNoOfChannels'],
            ]);
        });

        // save transceivers
        $transceivers = $this->syncModels($os->getDevice(), 'transceivers', $transceivers)->keyBy('index');

        echo "\nMetrics: ";
        ModuleModelObserver::observe(\App\Models\TransceiverMetric::class);
        $metric_data = \SnmpQuery::enumStrings()->walk('IPI-CMM-CHASSIS-MIB::cmmTransDDMTable')->table(3);
        $metrics = new Collection;

        foreach ($metric_data as $chassis => $chassis_data) {
            foreach ($chassis_data as $module => $module_data) {
                // get the transceiver and start a new metric collection
                $transceiver = $transceivers->get("$chassis.$module");
                $divisor = 100;

                foreach ($module_data as $channel => $channel_data) {

                    // temp
                    if ($channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTemperature'] != '-100001') {
                        $metrics->push(new TransceiverMetric([
                            'transceiver_id' => $transceiver->id,
                            'channel' => $channel,
                            'description' => $chassis . '/' . $module . '.' . $channel,
                            'type' => 'temperature',
                            'oid' => ".1.3.6.1.4.1.36673.100.1.2.3.1.2.$chassis.$module.$channel",
                            'value' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTemperature'] / $divisor,
                            'divisor' => $divisor,
                            'threshold_min_warning' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTempAlertThresholdMin'] / $divisor,
                            'threshold_max_warning' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTempAlertThresholdMax'] / $divisor,
                            'threshold_min_critical' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTempCriticalThresholdMin'] / $divisor,
                            'threshold_max_critical' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTempCriticalThresholdMax'] / $divisor,
                        ]));
                    }

                    // voltage
                    if ($channel_data['IPI-CMM-CHASSIS-MIB::cmmTransVoltage'] != '-100001') {
                        $metrics->push(new TransceiverMetric([
                            'transceiver_id' => $transceiver->id,
                            'channel' => $channel,
                            'description' => $chassis . '/' . $module . '.' . $channel,
                            'type' => 'voltage',
                            'oid' => ".1.3.6.1.4.1.36673.100.1.2.3.1.7.$chassis.$module.$channel",
                            'value' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransVoltage'] / $divisor,
                            'divisor' => $divisor,
                            'threshold_min_warning' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransVoltAlertThresholdMin'] / $divisor,
                            'threshold_max_warning' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransVoltAlertThresholdMax'] / $divisor,
                            'threshold_min_critical' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransVoltCriticalThresholdMin'] / $divisor,
                            'threshold_max_critical' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransVoltCriticalThresholdMax'] / $divisor,
                        ]));
                    }

                    // bias
                    if ($channel_data['IPI-CMM-CHASSIS-MIB::cmmTransLaserBiasCurrent'] != '-100001') {
                        $metrics->push(new TransceiverMetric([
                            'transceiver_id' => $transceiver->id,
                            'channel' => $channel,
                            'description' => $chassis . '/' . $module . '.' . $channel,
                            'type' => 'bias',
                            'oid' => ".1.3.6.1.4.1.36673.100.1.2.3.1.12.$chassis.$module.$channel",
                            'value' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransLaserBiasCurrent'] / $divisor,
                            'divisor' => $divisor,
                            'threshold_min_warning' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransLaserBiasCurrAlertThresholdMin'] / $divisor,
                            'threshold_max_warning' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransLaserBiasCurrAlertThresholdMax'] / $divisor,
                            'threshold_min_critical' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransLaserBiasCurrCriticalThresholdMin'] / $divisor,
                            'threshold_max_critical' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransLaserBiasCurrCriticalThresholdMax'] / $divisor,
                        ]));
                    }

                    // power-tx
                    if ($channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTxPowerSupported'] == 'supported') {
                        $metrics->push(new TransceiverMetric([
                            'transceiver_id' => $transceiver->id,
                            'channel' => $channel,
                            'description' => $chassis . '/' . $module . '.' . $channel,
                            'type' => 'power-tx',
                            'oid' => ".1.3.6.1.4.1.36673.100.1.2.3.1.17.$chassis.$module.$channel",
                            'value' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTxPower'] / $divisor,
                            'divisor' => $divisor,
                            'threshold_min_warning' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTxPowerAlertThresholdMin'] / $divisor,
                            'threshold_max_warning' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTxPowerAlertThresholdMax'] / $divisor,
                            'threshold_min_critical' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTxPowerCriticalThresholdMin'] / $divisor,
                            'threshold_max_critical' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransTxPowerCriticalThresholdMax'] / $divisor,
                        ]));
                    }

                    // power-rx
                    if ($channel_data['IPI-CMM-CHASSIS-MIB::cmmTransRxPowerSupported'] == 'supported') {
                        $metrics->push(new TransceiverMetric([
                            'transceiver_id' => $transceiver->id,
                            'channel' => $channel,
                            'description' => $chassis . '/' . $module . '.' . $channel,
                            'type' => 'power-rx',
                            'oid' => ".1.3.6.1.4.1.36673.100.1.2.3.1.22.$chassis.$module.$channel",
                            'value' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransRxPower'] / $divisor,
                            'divisor' => $divisor,
                            'threshold_min_warning' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransRxPowerAlertThresholdMin'] / $divisor,
                            'threshold_max_warning' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransRxPowerAlertThresholdMax'] / $divisor,
                            'threshold_min_critical' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransRxPowerCriticalThresholdMin'] / $divisor,
                            'threshold_max_critical' => $channel_data['IPI-CMM-CHASSIS-MIB::cmmTransRxPowerCriticalThresholdMax'] / $divisor,
                        ]));
                    }
                }
            }
        }

        // save metrics
        $this->syncModels($os->getDevice(), 'transceiverMetrics', $metrics);

        echo "\n";
    }

    public function poll(OS $os, DataStorageInterface $datastore): void
    {
        $metrics = $os->getDevice()->transceiverMetrics;
        $metrics->load('transceiver');

        $oids = $metrics->pluck('oid')->all();
        $data = \SnmpQuery::numeric()->get($oids)->values();

        /** @var TransceiverMetric $metric */
        foreach ($metrics as $metric) {
            $metric->value_prev = $metric->value;
            $metric->value = ! empty($data[$metric->oid]) ? $data[$metric->oid] * $metric->multiplier / $metric->divisor : null;
            $metric->save();
            Log::info("$metric->description $metric->type: $metric->value");

            $datastore->put($os->getDeviceArray(), 'transceiver-metric', [
                'type' => $metric->type,
                'rrd_def' => RrdDefinition::make()->addDataset('value', 'GAUGE'),
                'rrd_name' => ['transceiver-metric', $metric->type, $metric->transceiver->index],
            ], [
                'value' => $metric->value,
            ]);
        }
    }

    public function cleanup(Device $device): void
    {
        $device->transceiverMetrics()->delete();
        $device->transceivers()->delete();
    }

    public function dump(Device $device): array
    {
        return [
            'transceivers' => $device->transceivers()->orderBy('index')
                ->leftJoin('ports', 'transceivers.port_id', 'ports.port_id')
                ->select(['transceivers.*', 'ifIndex'])
                ->get()->map->makeHidden(['id', 'created_at', 'updated_at', 'device_id', 'port_id']),
            'transceiver_metrics' => $device->transceiverMetrics()
                ->orderBy('type')->orderBy('transceivers.index')->orderBy('channel')
                ->leftJoin('transceivers', 'transceivers.id', 'transceiver_metrics.transceiver_id')
                ->select(['transceiver_metrics.*', 'index'])
                ->get()->map->makeHidden(['id', 'created_at', 'updated_at', 'device_id', 'transceiver_id', 'value_prev', 'oid', 'description']),
        ];
    }
}

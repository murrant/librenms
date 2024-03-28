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
use App\Models\TransceiverMetric;
use App\Observers\ModuleModelObserver;
use Illuminate\Support\Facades\Log;
use LibreNMS\DB\SyncsModels;
use LibreNMS\Interfaces\Data\DataStorageInterface;
use LibreNMS\Interfaces\Discovery\TransceiverDiscovery;
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
        return $status->isEnabledAndDeviceUp($os->getDevice()) && $os instanceof TransceiverDiscovery;
    }

    public function shouldPoll(OS $os, ModuleStatus $status): bool
    {
        return $status->isEnabledAndDeviceUp($os->getDevice()) && $os instanceof TransceiverDiscovery && $os->getDevice()->transceiverMetrics()->exists();
    }

    public function discover(OS $os): void
    {
        if ($os instanceof TransceiverDiscovery) {
            echo "\nTransceivers: ";
            $transceivers = $os->discoverTransceivers();

            // save transceivers
            ModuleModelObserver::observe(\App\Models\Transceiver::class);
            $transceivers = $this->syncModels($os->getDevice(), 'transceivers', $transceivers);

            echo "\nMetrics: ";
            $metrics = $os->discoverTransceiverMetrics($transceivers->keyBy('index'));

            // save metrics
            ModuleModelObserver::observe(\App\Models\TransceiverMetric::class);
            $this->syncModels($os->getDevice(), 'transceiverMetrics', $metrics);

            echo "\n";
        }
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
            $metric->value = null;

            // transform the value to the proper scale
            if (! empty($data[$metric->oid])) {
                $value = $data[$metric->oid] * $metric->multiplier / $metric->divisor;
                if (isset($metric->transform_function) && is_callable($metric->transform_function)) {
                    dump($data[$metric->oid], $value, call_user_func($metric->transform_function, $value));
                    $value = call_user_func($metric->transform_function, $value);
                }

                $metric->value = $value;
            }
            $metric->save();

            Log::info($metric->transceiver->index . " $metric->type: $metric->value");

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

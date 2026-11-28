<?php

/**
 * Ports.php
 *
 * Interface discovery and polling
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2023 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Modules;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use App\Models\Port;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LibreNMS\Enum\PollingMethodType;
use LibreNMS\Interfaces\Data\DataStorageInterface;
use LibreNMS\Interfaces\Polling\PortDataSource;
use LibreNMS\OS;
use LibreNMS\Polling\Method\PollingMethodRegistry;
use LibreNMS\Ports\PortMeasurement;

class Ports extends LegacyModule
{
    public function __construct()
    {
        parent::__construct('ports');
    }

    public function pollingMethods(Device $device): array
    {
        if (! $this->useNewModule()) {
            return parent::pollingMethods($device);
        }

        return [PollingMethodType::Snmp, ...app(PollingMethodRegistry::class)->providing(PortDataSource::class)];
    }

    public function discover(OS $os): void
    {
        if (! $this->useNewModule()) {
            parent::discover($os);

            return;
        }

        $this->source($os)?->discoverPorts()->sync($os->getDevice());
    }

    public function poll(OS $os, DataStorageInterface $datastore): void
    {
        if (! $this->useNewModule()) {
            parent::poll($os, $datastore);

            return;
        }

        $device = $os->getDevice();
        $ports = $device->ports()->isValid()->with('statistics')->get()->keyBy('ifIndex');
        if ($ports->isEmpty()) {
            return;
        }

        $time = now()->timestamp;
        $rows = $this->source($os)?->pollPorts($ports->all(), $device->selectedPortPolling()->isEnabled());
        if (empty($rows)) {
            return;
        }

        $samples = DB::transaction(fn () => $this->recordSamples($device, $ports, $rows, $time));
        $measurement = new PortMeasurement;
        foreach ($samples as $index => $counters) {
            $measurement->store($datastore, $device, $ports[$index], $counters, $rows[$index]);
        }
    }

    /** @param Collection<int, Port> $ports */
    private function recordSamples(Device $device, Collection $ports, array $rows, int $time): array
    {
        $base = $extended = $samples = [];
        foreach ($ports as $index => $port) {
            $row = $rows[$index] ?? null;
            if (! $row || $port->poll_time >= $time) {
                continue;
            }
            PortMeasurement::updateLink($device, $port, $row);
            if (! empty($row['skipCounters'])) {
                continue;
            }
            [$counters, $statistics] = PortMeasurement::sample($port, $row, $time);
            $base[] = ['port_id' => $port->port_id] + $counters;
            $extended[] = ['port_id' => $port->port_id] + $statistics;
            $samples[$index] = $counters + $statistics;
        }
        foreach (['ports' => $base, 'ports_statistics' => $extended] as $table => $values) {
            foreach (array_chunk($values, 200) as $chunk) {
                DB::table($table)->upsert($chunk, ['port_id']);
            }
        }

        return $samples;
    }

    private function useNewModule(): bool
    {
        return (int) LibrenmsConfig::get('modules.ports.version') === 2;
    }

    private function source(OS $os): ?PortDataSource
    {
        $device = $os->getDevice();
        // Use the same configured method for inventory and counters, even when its probe fails.
        foreach ($this->pollingMethods($device) as $method) {
            if ($device->polling()->isEnabled($method)) {
                return $os->dataSource($method, PortDataSource::class);
            }
        }

        return null;
    }
}

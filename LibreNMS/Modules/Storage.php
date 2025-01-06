<?php
/**
 * Storage.php
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2021 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Modules;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use App\Observers\ModuleModelObserver;
use LibreNMS\DB\SyncsModels;
use LibreNMS\Interfaces\Data\DataStorageInterface;
use LibreNMS\Interfaces\Discovery\StorageDiscovery;
use LibreNMS\OS;
use LibreNMS\Polling\ModuleStatus;
use LibreNMS\RRD\RrdDefinition;
use LibreNMS\Util\Number;

class Storage implements \LibreNMS\Interfaces\Module
{
    use SyncsModels;

    /**
     * An array of all modules this module depends on
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * Should this module be run?
     */
    public function shouldDiscover(OS $os, ModuleStatus $status): bool
    {
        return $status->isEnabledAndDeviceUp($os->getDevice());
    }

    /**
     * Should polling run for this device?
     */
    public function shouldPoll(OS $os, ModuleStatus $status): bool
    {
        return $status->isEnabledAndDeviceUp($os->getDevice());
    }


    /**
     * @inheritDoc
     */
    public function discover(OS $os): void
    {
        if ($os instanceof StorageDiscovery) {
            $data = $os->discoverStorage()->filter->isValid($os->getName());

            ModuleModelObserver::observe(\App\Models\Storage::class);
            $this->syncModels($os->getDevice(), 'storage', $data);
        }
    }

    /**
     * @inheritDoc
     */
    public function poll(OS $os, DataStorageInterface $datastore): void
    {
        foreach ($os->getDevice()->storage as $storageModel) {
            echo 'Storage ' . $storageModel->storage_descr . ': ' . $storageModel->storage_mib . "\n\n\n\n";

            // variables for legacy code
            $storage = $storageModel->toArray();
            $device = $os->getDeviceArray();
            $file = LibrenmsConfig::get('install_dir') . '/includes/polling/storage/' . $storageModel->storage_mib . '.inc.php';
            if (is_file($file)) {
                include $file;
            }

            d_echo($storage);

            $percent = Number::calculatePercent($storage['used'], $storage['size']);
            echo $percent . '% ';

            $fields = [
                'used'   => $storage['used'],
                'free'   => $storage['free'],
            ];

            $tags = [
                'mib' => $storageModel->storage_mib,
                'descr' => $storageModel->storage_descr,
                'rrd_name' => ['storage', $storageModel->storage_mib, $storageModel->storage_descr],
                'rrd_def' => RrdDefinition::make()
                    ->addDataset('used', 'GAUGE', 0)
                    ->addDataset('free', 'GAUGE', 0),
            ];
            $datastore->put($os->getDeviceArray(), 'storage', $tags, $fields);

            // NOTE: casting to string for mysqli bug (fixed by mysqlnd)
            $storageModel->storage_used = $storage['used'];
            $storageModel->storage_free = $storage['free'];
            $storageModel->storage_size = $storage['size'];
            $storageModel->storage_units = $storage['units'];
            $storageModel->storage_perc = $percent;
            $storageModel->save();

            echo "\n";
        }
    }

    public function dataExists(Device $device): bool
    {
        return $device->storage()->exists();
    }

    /**
     * @inheritDoc
     */
    public function cleanup(Device $device): int
    {
        return $device->storage()->delete();
    }

    public function dump(Device $device, string $type): ?array
    {
        return [
            'storage' => $device->storage()
                ->orderBy('storage_index')
                ->orderBy('storage_type')
                ->get()->map->makeHidden(['device_id', 'storage_id']),
        ];
    }
}

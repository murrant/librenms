<?php
/*
 * YamlDeviceDiscovery.php
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
 * @copyright  2023 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\OS\Traits;

use App\Models\Storage;
use Illuminate\Support\Collection;
use LibreNMS\Discovery\Yaml\IndexField;
use LibreNMS\Discovery\Yaml\OidField;
use LibreNMS\Discovery\Yaml\YamlDiscoveryField;
use LibreNMS\Discovery\YamlDiscoveryDefinition;

trait YamlStorageDiscovery
{
    private array $storagePrefetch = [];

    public function discoverYamlStorage(): Collection
    {
        $discovery = YamlDiscoveryDefinition::make(Storage::class)
            ->addField(new YamlDiscoveryField('type', 'storage_type', 'Storage'))
            ->addField(new YamlDiscoveryField('type', 'storage_type', 'Storage'))
            ->addField(new YamlDiscoveryField('descr', 'storage_descr', 'Disk {{ $index }}'))
            ->addField(new YamlDiscoveryField('units', 'storage_units', 1048576)) // TODO good default?
            ->addField(new OidField('size', 'storage_size'))
            ->addField(new OidField('used', 'storage_used', priority: 3))
            ->addField(new OidField('free', 'storage_free', priority: 2))
            ->addField(new OidField('percent_used', 'storage_perc', priority: 1))
            ->addField(new IndexField('index', 'storage_index', '{{ $index }}'))
            ->afterEach(function (Storage $storage, YamlDiscoveryDefinition $def, $yaml, $index) {
                // fill missing values
                $storage->fillUsage(
                    $storage->storage_used,
                    $storage->storage_size,
                    $storage->storage_free,
                    $storage->storage_perc,
                );
            });

        return $discovery->discover($this->getDiscovery('storage'));
    }
}
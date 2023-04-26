<?php
/**
 * Device.php
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
 * @copyright  2019 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Cache;

class Device
{
    /** @var \App\Models\Device[] */
    private array $devices = [];
    private ?int $primary = null;

    /**
     * Gets the current primary device.
     *
     * @return \App\Models\Device
     */
    public function getPrimary(): \App\Models\Device
    {
        return $this->get($this->primary);
    }

    /**
     * Set the primary device.
     * This will be fetched by getPrimary()
     *
     * @param  int  $device_id
     */
    public function setPrimary(int $device_id): void
    {
        $this->primary = $device_id;
    }

    /**
     * Check if a primary device is set
     */
    public function hasPrimary(): bool
    {
        return $this->primary !== null;
    }

    /**
     * Get a device by device_id or hostname
     *
     * @param  int|string|null  $device  device_id or hostname
     * @return \App\Models\Device
     */
    public function get(int|string|null $device): \App\Models\Device
    {
        if ($device === null) {
            return new \App\Models\Device;
        }

        // if string input is not an integer, get by hostname
        if (is_string($device) && ! ctype_digit($device)) {
            return $this->getByHostname($device);
        }

        // device is not be loaded, try to load it
        return $this->devices[$device] ?? $this->load($device);
    }

    /**
     * Get a device by hostname
     *
     * @param  string|null  $hostname
     * @return \App\Models\Device
     */
    public function getByHostname($hostname): \App\Models\Device
    {
        return $this->getByField('hostname', $hostname);
    }

    /**
     * Get device by any device field or a number of fields
     * Slower than by device_id, but attempts to prevent an sql query
     */
    public function getByField(string|array $fields, mixed $value): \App\Models\Device
    {
        $fields = (array)$fields;

        foreach ($fields as $field) {
            $device_id = array_column($this->devices, 'device_id', $field)[$value] ?? 0;
            if ($device_id) {
                break;
            }
        }

        return $this->devices[$device_id] ?? $this->load($value, $fields);
    }

    /**
     * Ignore cache and load the device fresh from the database
     *
     * @param  int  $device_id
     * @return \App\Models\Device
     */
    public function refresh(?int $device_id): \App\Models\Device
    {
        unset($this->devices[$device_id]);

        return $this->get($device_id);
    }

    /**
     * Flush the cache
     */
    public function flush(): void
    {
        $this->devices = [];
    }

    /**
     * Check if the device id is currently loaded into cache
     */
    public function has(int $device_id): bool
    {
        return isset($this->devices[$device_id]);
    }

    private function load(mixed $value, string|array $field = ['device_id']): \App\Models\Device
    {
        $query = \App\Models\Device::query();
        foreach ((array) $field as $column) {
            if ($column == 'ip') {
                $value = inet_pton($value); // convert IP to binary for query
                if ($value === false) {
                    continue;  // not an IP, skip the ip field
                }
            }

            $query->orWhere($column, $value);
        }

        $device = $query->first();

        if (! $device) {
            return new \App\Models\Device;
        }

        $this->devices[$device->device_id] = $device;

        return $device;
    }

    /**
     * Insert a fake device into the cache to avoid database lookups
     * Will not work with relationships unless they are pre-populated (and not using a relationship based query)
     */
    public function fake(\App\Models\Device $device): \App\Models\Device
    {
        if (empty($device->device_id)) {
            // find a free device_id
            $device->device_id = 1;
            while (isset($this->devices[$device->device_id])) {
                $device->device_id++;
            }
        }

        $device->exists = true; // fake that device is saved to database
        $this->devices[$device->device_id] = $device;

        return $device;
    }
}

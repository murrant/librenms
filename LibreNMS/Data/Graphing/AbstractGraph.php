<?php

/**
 * AbstractGraph.php
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
 * @copyright  2026 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Data\Graphing;

use App\Facades\DeviceCache;
use App\Facades\PortCache;
use App\Models\Device;
use App\Models\Port;
use LibreNMS\Interfaces\Data\Graphing\GraphInterface;

abstract class AbstractGraph implements GraphInterface
{
    protected Device $device;
    protected ?Port $port;

    public function __construct(
        protected readonly GraphParameters $params,
        protected readonly array $vars = [],
    )
    {
        $device_id = $this->vars['device'] ?? ($this->params->type == 'device' ? ($this->vars['id'] ?? null) : null);
        $this->device = DeviceCache::get($device_id);

        $port_id = $this->vars['port'] ?? ($this->params->type == 'port' ? ($this->vars['id'] ?? null) : null);
        $this->port = PortCache::get($port_id);
    }

    public function getParams(): GraphParameters
    {
        return $this->params;
    }

    public function validation(): array
    {
        return [
            'type' => ['required', 'string', 'regex:/^[a-z][a-z0-9]*_[a-zA-Z0-9_]+$/'],
            'id' => ['nullable', 'integer'],
            'from' => ['nullable', 'regex:/^(-?\d+|-?\d+[smhdwMy]|now|end)$/'],
            'to' => ['nullable', 'regex:/^(-?\d+|-?\d+[smhdwMy]|now|end)$/'],
            'width' => ['nullable', 'integer', 'min:10', 'max:10000'],
            'height' => ['nullable', 'integer', 'min:10', 'max:8000'],
            'legend' => ['nullable', 'in:yes,no,0,1'],
            'bg' => ['nullable', 'regex:/^[0-9A-Fa-f]{6}$/'],
            'title' => ['nullable', 'string', 'max:255'],
            'output' => ['nullable', 'in:png,svg,json'],
        ];
    }

    public function getPageTitle(): string
    {
        return $this->getGraphTitle();
    }

    protected function init(): void
    {
        // for child init
    }
}

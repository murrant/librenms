<?php

/**
 * LegacyService.php
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
 * @copyright  2025 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Services;

use App\Facades\DeviceCache;
use LibreNMS\Util\Clean;

class LegacyService extends Service
{
    private string $script;

    public function __construct(string $type)
    {
        parent::__construct($type);
        $this->script = self::getScriptPath($type);
    }

    public static function getScriptPath(string $type): string
    {
        return base_path('includes/services/check_' . Clean::fileName($type) . '.inc.php');
    }

    public function buildCommand(\App\Models\Service $service): array
    {
        $data = [
            'service' => array_merge(DeviceCache::get($service->device_id)->toArray(), $service->toArray()),
        ];

        $vars = $this->loadScript($data);

        if (isset($vars['check_cmd'])) {
            return explode(' ', $vars['check_cmd']);
        }

        return parent::buildCommand($service);
    }

    public function dataSets(string $rrd_filename = '', ?string $ds = null): array
    {
        $vars = $this->loadScript(['rrd_filename' => $rrd_filename]);

        if (isset($vars['check_ds'])) {
            $dataSets = [];
            $sets = json_decode($vars['check_ds'], true);
            $graphs = $vars['check_graph'] ?? [];
            foreach ($sets as $name => $unit) {
                $commands = $graphs[$name] ?? $this->defaultGraphCommands($rrd_filename, $name);
                $dataSets[] = new ServiceDataSet($name, $unit, is_array($commands) ? $commands : [$commands]);
            }

            return $dataSets;
        }

        return parent::dataSets($rrd_filename, $ds);
    }

    private function loadScript(array $vars = []): array
    {
        if (empty($vars['service'])) {
            $vars['service'] = [
                'service_id' => 0,
                'device_id' => 0,
                'service_ip' => '',
                'service_type' => "$this->type",
                'service_desc' => '',
                'service_param' => '',
                'service_ignore' => false,
                'service_status' => 0,
                'service_message' => 'message',
                'service_disabled' => false,
                'service_ds' => '{}',
                'service_template_id' => 0,
                'service_name' => 'name',
                'hostname' => '127.0.0.1',
                'overwrite_ip' => '',
            ];
        }

        extract($vars);

        include $this->script;

        return get_defined_vars();
    }
}

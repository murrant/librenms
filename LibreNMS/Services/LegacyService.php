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

class LegacyService extends Service
{
    private string $params;
    private string $check_cmd;
    private string $check_ds;
    private array $check_graph;

    public function __construct(public readonly string $type) {
        $vars = $this->loadScript();
        $this->params = $vars['params'] ?? '';
        $this->check_cmd = $vars['check_cmd'] ?? '';
        $this->check_ds = $vars['check_ds'] ?? '';
        $this->check_graph = $vars['check_graph'] ?? [];
    }

    public function buildCommand(Device $device, \App\Models\Service $service, array $parameters = []): array
    {

    }


    private function loadScript(array $vars = []): array
    {
        $check_script = $this->getExecutable();
        if (! is_file($check_script)) {
            return [];
        }

        extract($vars);

        include $check_script;

        return get_defined_vars();
    }
}

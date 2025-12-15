<?php
/**
 * Service.php
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

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use LibreNMS\Exceptions\ServiceCheckNotExecutableException;
use LibreNMS\Exceptions\ServiceCheckNotFoundException;
use LibreNMS\Exceptions\ServiceException;
use LibreNMS\Util\Clean;

class Service
{

    public function __construct(
        public readonly string $type = '',
    ) {}

    /**
     * @param  Device  $device
     * @param  array<string, string>  $values
     * @return string[]
     * @throws ServiceException
     */
    public function buildCommand(Device $device, Service $service,array $values = []): array
    {
        $bin = $this->getExecutable();

        if (! is_file($bin)) {
            throw new ServiceCheckNotFoundException;
        }

        if (! is_executable($bin)) {
            throw new ServiceCheckNotExecutableException;
        }

        $command = [$bin];
        foreach ($this->parameters() as $parameter) {
            if (isset($values[$parameter->name])) {
                $command = array_merge($command, $parameter->format($values[$parameter->name]));
            }
        }

        return $command;
    }

    /**
     * @return array<ServiceArgument|ServiceOption>
     */
    public function parameters(): array
    {
        return [];
    }

    /**
     * @return array<ServiceDataSet>
     */
    public function dataSets(string $rrd_filename = '', ?string $ds = null): array
    {
        if (empty($ds)) {
            return [];
        }

        $tint = preg_match('/loss/i', $ds) ? 'pinks' : 'blues';
        $color_avg = LibrenmsConfig::get("graph_colours.$tint.2");
        $color_max = LibrenmsConfig::get("graph_colours.$tint.0");

        return [
            new ServiceDataSet($ds, '', [
               'DEF:DS=' . $rrd_filename . ':' . $ds . ':AVERAGE',
               'DEF:DS_MAX=' . $rrd_filename . ':' . $ds . ':MAX',
               'AREA:DS_MAX#' . $color_max . ':',
               'AREA:DS#' . $color_avg . ':' . str_pad(substr(ucfirst($ds), 0, 15), 15),
               'GPRINT:DS:LAST:%5.2lf%s',
               'GPRINT:DS:AVERAGE:%5.2lf%s',
               'GPRINT:DS_MAX:MAX:%5.2lf%s\\l',
            ]),
        ];
    }

    /**
     * @return array<string, array{unit: string, commands: string[]}>
     */
    public function graph(string $rrd_filename = ''): array
    {
        return [];
    }

    protected function getExecutable(): string
    {
        return LibrenmsConfig::get('nagios_plugins') . '/check_' . Clean::fileName(strtolower($this->type));
    }
}

<?php

/**
 * CheckLoad.php
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

namespace LibreNMS\Services\Checks;

use App\Facades\LibrenmsConfig;
use LibreNMS\Services\Service;
use LibreNMS\Services\ServiceDataSet;
use LibreNMS\Services\ServiceOption;

class CheckLoad extends Service
{
    public function parameters(): array
    {
        return [
            new ServiceOption('warning', 'w', default: '5,5,5'),
            new ServiceOption('critical', 'c', default: '10,10,10'),
            new ServiceOption('percpu', 'p', ['percpu' => 'int']),
            new ServiceOption('procs-to-show'),
        ];
    }

    public function dataSets(string $rrd_filename = '', ?string $ds = null): array
    {
        return [
            new ServiceDataSet('load', 'processes', [
                'DEF:DS0=' . $rrd_filename . ':load1:AVERAGE',
                'LINE1.25:DS0#' . LibrenmsConfig::get('graph_colours.mixed.0') . ':' . str_pad(substr('Load 1', 0, 15), 15),
                'GPRINT:DS0:LAST:%5.2lf%s',
                'GPRINT:DS0:AVERAGE:%5.2lf%s',
                'GPRINT:DS0:MAX:%5.2lf%s\\l',
                'DEF:DS1=' . $rrd_filename . ':load5:AVERAGE',
                'LINE1.25:DS1#' . LibrenmsConfig::get('graph_colours.mixed.1') . ':' . str_pad(substr('Load 5', 0, 15), 15),
                'GPRINT:DS1:LAST:%5.2lf%s',
                'GPRINT:DS1:AVERAGE:%5.2lf%s',
                'GPRINT:DS1:MAX:%5.2lf%s\\l',
                'DEF:DS2=' . $rrd_filename . ':load15:AVERAGE',
                'LINE1.25:DS2#' . LibrenmsConfig::get('graph_colours.mixed.2') . ':' . str_pad(substr('Load 15', 0, 15), 15),
                'GPRINT:DS2:LAST:%5.2lf%s',
                'GPRINT:DS2:AVERAGE:%5.2lf%s',
                'GPRINT:DS2:MAX:%5.2lf%s\\l',
            ]),
        ];
    }
}

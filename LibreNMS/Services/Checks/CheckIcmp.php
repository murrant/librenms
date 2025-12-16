<?php

/**
 * CheckIcmp.php
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
use Symfony\Component\Console\Input\InputOption;

class CheckIcmp extends Service
{
    public function parameters(): array
    {
        return [
            new ServiceOption('ipv4-only', '4', mode: InputOption::VALUE_NONE),
            new ServiceOption('ipv6-only', '6', mode: InputOption::VALUE_NONE),
            new ServiceOption('warning', 'w'),
            new ServiceOption('critical', 'c'),
            new ServiceOption('rta-mode-thresholds', 'R'),
            new ServiceOption('packet-loss-mode-thresholds', 'P'),
            new ServiceOption('jitter-mode-thresholds', 'J'),
            new ServiceOption('mos-mode-thresholds', 'M'),
            new ServiceOption('score-mode-thresholds', 'S'),
            new ServiceOption('out-of-order-packets', 'O', mode: InputOption::VALUE_NONE),
            new ServiceOption('number-of-packets', 'n', ['number-of-packets' => 'int|min:0']),
            new ServiceOption('target-interval', 'I'),
            new ServiceOption('minimal-host-alive', 'm', ['minimal-host-alive' => 'int|min:0']),
            new ServiceOption('outgoing-ttl', 'l', ['outgoing-ttl' => 'int|min:0']),
            new ServiceOption('timeout', 't'),
            new ServiceOption('bytes', 'b'),
            new ServiceOption('verbose', 'v', mode: InputOption::VALUE_NONE | InputOption::VALUE_IS_ARRAY),
            new ServiceOption('Host', 'H', ['Host' => 'required|ip_or_hostname'], mode: InputOption::VALUE_IS_ARRAY),
        ];
    }

    public function dataSets(string $rrd_filename = '', ?string $ds = null): array
    {
        $dataSets = [
            new ServiceDataSet('rta', 's', [
                'DEF:DS0=' . $rrd_filename . ':rta:AVERAGE',
                'LINE1.25:DS0#' . LibrenmsConfig::get('graph_colours.mixed.0') . ':Round Trip Avg ',
                'GPRINT:DS0:LAST:%5.2lf%s',
                'GPRINT:DS0:AVERAGE:%5.2lf%s',
                'GPRINT:DS0:MAX:%5.2lf%s\\l',
            ]),
            new ServiceDataSet('rtmax', 's', [
                'DEF:DS1=' . $rrd_filename . ':rtmax:AVERAGE',
                'LINE1.25:DS1#' . LibrenmsConfig::get('graph_colours.mixed.1') . ':Round Trip Max ',
                'GPRINT:DS1:LAST:%5.2lf%s',
                'GPRINT:DS1:AVERAGE:%5.2lf%s',
                'GPRINT:DS1:MAX:%5.2lf%s\\l',
            ]),
            new ServiceDataSet('rtmin', 's', [
                'DEF:DS2=' . $rrd_filename . ':rtmin:AVERAGE',
                'LINE1.25:DS2#' . LibrenmsConfig::get('graph_colours.mixed.2') . ':Round Trip Min ',
                'GPRINT:DS2:LAST:%5.2lf%s',
                'GPRINT:DS2:AVERAGE:%5.2lf%s',
                'GPRINT:DS2:MAX:%5.2lf%s\\l',
            ]),
            new ServiceDataSet('pl', '%', [
                'DEF:DS0=' . $rrd_filename . ':pl:AVERAGE',
                'AREA:DS0#' . LibrenmsConfig::get('graph_colours.mixed.2') . ':Packet Loss (%)',
                'GPRINT:DS0:LAST:%5.2lf%s',
                'GPRINT:DS0:AVERAGE:%5.2lf%s',
                'GPRINT:DS0:MAX:%5.2lf%s\\l',
            ]),
        ];

        return array_filter($dataSets, fn ($dataSet) => $ds === null || $ds === $dataSet->name);
    }

    public function graph(string $rrd_filename = ''): array
    {
        return [
            'rta' => [
                'unit' => 's',
                'commands' => [
                    'DEF:DS0=' . $rrd_filename . ':rta:AVERAGE',
                    'LINE1.25:DS0#' . LibrenmsConfig::get('graph_colours.mixed.0') . ':' . str_pad(substr('Round Trip Average', 0, 15), 15),
                    'GPRINT:DS0:LAST:%5.2lf%s',
                    'GPRINT:DS0:AVERAGE:%5.2lf%s',
                    'GPRINT:DS0:MAX:%5.2lf%s\\l',
                ],
            ],
            'rtmax' => [
                'unit' => 's',
                'commands' => [
                    'DEF:DS1=' . $rrd_filename . ':rtmax:AVERAGE',
                    'LINE1.25:DS1#' . LibrenmsConfig::get('graph_colours.mixed.1') . ':' . str_pad(substr('Round Trip Max', 0, 15), 15),
                    'GPRINT:DS1:LAST:%5.2lf%s',
                    'GPRINT:DS1:AVERAGE:%5.2lf%s',
                    'GPRINT:DS1:MAX:%5.2lf%s\\l',
                ],
            ],
            'rtmin' => [
                'unit' => 's',
                'commands' => [
                    'DEF:DS2=' . $rrd_filename . ':rtmin:AVERAGE',
                    'LINE1.25:DS2#' . LibrenmsConfig::get('graph_colours.mixed.2') . ':' . str_pad(substr('Round Trip Min', 0, 15), 15),
                    'GPRINT:DS2:LAST:%5.2lf%s',
                    'GPRINT:DS2:AVERAGE:%5.2lf%s',
                    'GPRINT:DS2:MAX:%5.2lf%s\\l',
                ],
            ],
            'pl' => [
                'DEF:DS0=' . $rrd_filename . ':pl:AVERAGE',
                'AREA:DS0#' . LibrenmsConfig::get('graph_colours.mixed.2') . ':' . str_pad(substr('Packet Loss (%)', 0, 15), 15),
                'GPRINT:DS0:LAST:%5.2lf%s',
                'GPRINT:DS0:AVERAGE:%5.2lf%s',
                'GPRINT:DS0:MAX:%5.2lf%s\\l',
            ],
        ];
    }
}

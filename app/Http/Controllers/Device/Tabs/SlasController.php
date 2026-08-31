<?php

/**
 * SlasController.php
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
 * @copyright  2020 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Http\Controllers\Device\Tabs;

use App\Models\Device;
use Illuminate\Http\Request;
use LibreNMS\Interfaces\UI\DeviceTab;
use LibreNMS\Util\Url;

class SlasController implements DeviceTab
{
    public function visible(Device $device): bool
    {
        return $device->slas()->exists();
    }

    public function slug(): string
    {
        return 'slas';
    }

    public function icon(): string
    {
        return 'fa-flag';
    }

    public function name(): string
    {
        return __('SLAs');
    }

    public function data(Device $device, Request $request): array
    {
        $slaId = Url::parseOptions('id');
        if ($slaId) {
            $sla = $device->slas()->where('deleted', 0)->findOrFail($slaId);
            $typeName = trans("modules.slas.{$sla->rtt_type}") ?: ucfirst((string) $sla->rtt_type);
            $slaName = 'SLA #' . $sla->sla_nr . ' - ' . $typeName;
            if ($sla->tag) {
                $slaName .= ': ' . $sla->tag;
            }
            if ($sla->owner) {
                $slaName .= ' (Owner: ' . $sla->owner . ')';
            }

            $detailGraphs = match ($sla->rtt_type) {
                'jitter', 'icmpjitter' => [
                    ['title' => __('Round Trip Time'), 'type' => 'device_sla'],
                    ['title' => __('Average Latency One Way'), 'type' => 'device_sla_jitter-latency'],
                    ['title' => __('Average Jitter'), 'type' => 'device_sla_jitter'],
                    ['title' => __('Packet Loss (Percent)'), 'type' => 'device_sla_jitter-loss-percent'],
                    ['title' => __('Packet Loss (Count)'), 'type' => 'device_sla_jitter-loss'],
                    ['title' => __('Lost Packets (Out Of Sequence, Tail Drop, Late Arrival)'), 'type' => 'device_sla_jitter-lost'],
                    ['title' => __('Mean Opinion Score'), 'type' => 'device_sla_jitter-mos'],
                    ['title' => __('Impairment / Calculated Planning Impairment Factor'), 'type' => 'device_sla_jitter-icpif'],
                ],
                'IcmpEcho' => [
                    ['title' => __('Round Trip Time'), 'type' => 'device_sla'],
                    ['title' => __('Packet Loss'), 'type' => 'device_sla_IcmpEcho'],
                ],
                'IcmpTimeStamp' => [
                    ['title' => __('Round Trip Time'), 'type' => 'device_sla'],
                    ['title' => __('Packet Loss'), 'type' => 'device_sla_IcmpTimeStamp'],
                ],
                'icmpAppl' => [
                    ['title' => __('Round Trip Time'), 'type' => 'device_sla'],
                    ['title' => __('Packet Loss'), 'type' => 'device_sla_icmpAppl'],
                ],
                default => [
                    ['title' => __('Round Trip Time'), 'type' => 'device_sla'],
                ],
            };

            return [
                'mode' => 'detail',
                'sla' => $sla,
                'sla_name' => $slaName,
                'is_danger' => $sla->opstatus == 2,
                'detail_graphs' => $detailGraphs,
            ];
        }

        $slas = $device->slas()->where('deleted', 0)->orderBy('sla_nr')->get();

        $types = ['all' => __('All')];
        foreach ($slas as $sla) {
            $types[$sla->rtt_type] = trans("modules.slas.{$sla->rtt_type}") ?: ucfirst((string) $sla->rtt_type);
        }
        asort($types);

        $view = Url::parseOptions('view', 'all');
        $opstatus = Url::parseOptions('opstatus', 'all');

        $typeOptions = [];
        foreach ($types as $typeKey => $typeText) {
            $typeOptions[$typeKey] = [
                'text' => $typeText,
                'link' => route('device', ['device' => $device, 'tab' => 'slas', 'vars' => 'view=' . $typeKey . '/opstatus=' . $opstatus]),
            ];
        }

        $statusOptions = [
            'all' => [
                'text' => __('All'),
                'link' => route('device', ['device' => $device, 'tab' => 'slas', 'vars' => 'view=' . $view . '/opstatus=all']),
            ],
            'up' => [
                'text' => __('Up'),
                'link' => route('device', ['device' => $device, 'tab' => 'slas', 'vars' => 'view=' . $view . '/opstatus=up']),
            ],
            'down' => [
                'text' => __('Down'),
                'link' => route('device', ['device' => $device, 'tab' => 'slas', 'vars' => 'view=' . $view . '/opstatus=down']),
            ],
        ];

        $filteredSlas = $slas->filter(function ($sla) use ($view, $opstatus) {
            if ($view !== 'all' && $sla->rtt_type !== $view) {
                return false;
            }
            $status = ($sla->opstatus === 0) ? 'up' : 'down';
            if ($opstatus !== 'all' && $status !== $opstatus) {
                return false;
            }

            return true;
        })->map(function ($sla) use ($device, $types) {
            $typeName = $types[$sla->rtt_type] ?? ucfirst((string) $sla->rtt_type);
            $name = 'SLA #' . $sla->sla_nr . ' - ' . $typeName;
            if ($sla->tag) {
                $name .= ': ' . $sla->tag;
            }
            if ($sla->owner) {
                $name .= ' (Owner: ' . $sla->owner . ')';
            }

            $hasDetail = in_array($sla->rtt_type, ['jitter', 'icmpjitter', 'IcmpEcho', 'IcmpTimeStamp', 'icmpAppl'], true);

            return [
                'id' => $sla->sla_id,
                'name' => $name,
                'has_detail' => $hasDetail,
                'detail_link' => $hasDetail ? route('device', ['device' => $device, 'tab' => 'slas', 'vars' => 'id=' . $sla->sla_id]) : null,
                'is_danger' => $sla->opstatus == 2,
            ];
        });

        return [
            'mode' => 'list',
            'view' => $view,
            'opstatus' => $opstatus,
            'type_options' => $typeOptions,
            'status_options' => $statusOptions,
            'slas' => $filteredSlas,
        ];
    }
}

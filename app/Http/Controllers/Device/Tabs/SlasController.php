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
use App\Models\Sla;
use Illuminate\Http\Request;
use LibreNMS\Interfaces\UI\DeviceTab;

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
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'view' => 'nullable|string',
            'opstatus' => 'nullable|in:all,up,down',
        ]);

        if (! empty($validated['id'])) {
            $sla = $device->slas()->where('deleted', 0)->findOrFail($validated['id']);

            return $this->detailData($sla);
        }

        return $this->listData($device, $validated['view'] ?? 'all', $validated['opstatus'] ?? 'all');
    }

    private function detailData(Sla $sla): array
    {
        return [
            'mode' => 'detail',
            'sla' => $sla,
            'sla_name' => $this->formatSlaName($sla),
            'is_danger' => $sla->opstatus == 2,
            'detail_graphs' => $this->detailGraphs($sla->rtt_type),
        ];
    }

    private function listData(Device $device, string $view, string $opstatus): array
    {
        $slas = $device->slas()->where('deleted', 0)->orderBy('sla_nr')->get();

        $types = ['all' => __('All')] + $slas->pluck('rtt_type', 'rtt_type')
            ->map(fn ($type) => $this->translateType($type))
            ->sort()
            ->all();

        $filteredSlas = $slas
            ->when($view !== 'all', fn ($query) => $query->where('rtt_type', $view))
            ->when($opstatus !== 'all', fn ($query) => $query->filter(
                fn (Sla $sla) => (($sla->opstatus === 0) ? 'up' : 'down') === $opstatus
            ))
            ->map(fn (Sla $sla) => [
                'id' => $sla->sla_id,
                'name' => $this->formatSlaName($sla),
                'has_detail' => $this->hasDetail($sla->rtt_type),
                'detail_link' => $this->hasDetail($sla->rtt_type) ? route('device', ['device' => $device, 'tab' => 'slas', 'id' => $sla->sla_id]) : null,
                'is_danger' => $sla->opstatus == 2,
            ]);

        $typeOptions = collect($types)->map(fn ($text, $typeKey) => [
            'text' => $text,
            'link' => route('device', ['device' => $device, 'tab' => 'slas', 'view' => $typeKey, 'opstatus' => $opstatus]),
        ])->all();

        $statusOptions = [
            'all' => ['text' => __('All'), 'link' => route('device', ['device' => $device, 'tab' => 'slas', 'view' => $view, 'opstatus' => 'all'])],
            'up' => ['text' => __('Up'), 'link' => route('device', ['device' => $device, 'tab' => 'slas', 'view' => $view, 'opstatus' => 'up'])],
            'down' => ['text' => __('Down'), 'link' => route('device', ['device' => $device, 'tab' => 'slas', 'view' => $view, 'opstatus' => 'down'])],
        ];

        return [
            'mode' => 'list',
            'view' => $view,
            'opstatus' => $opstatus,
            'type_options' => $typeOptions,
            'status_options' => $statusOptions,
            'slas' => $filteredSlas,
        ];
    }

    private function formatSlaName(Sla $sla): string
    {
        $name = 'SLA #' . $sla->sla_nr . ' - ' . $this->translateType($sla->rtt_type);
        if ($sla->tag) {
            $name .= ': ' . $sla->tag;
        }
        if ($sla->owner) {
            $name .= ' (Owner: ' . $sla->owner . ')';
        }

        return $name;
    }

    private function detailGraphs(?string $rttType): array
    {
        return match ($rttType) {
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
    }

    private function translateType(?string $rttType): string
    {
        return trans()->has("modules.slas.types.$rttType")
            ? trans("modules.slas.types.$rttType")
            : (string) $rttType;
    }

    private function hasDetail(?string $rttType): bool
    {
        return in_array(strtolower((string) $rttType), ['jitter', 'icmpjitter', 'icmpecho', 'icmptimestamp', 'icmpappl'], true);
    }
}

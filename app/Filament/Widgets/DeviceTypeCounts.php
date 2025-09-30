<?php

namespace App\Filament\Widgets;

use App\Facades\LibrenmsConfig;
use App\Filament\Resources\Devices\DeviceResource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeviceTypeCounts extends BaseWidget
{
    protected function getStats(): array
    {
        $deviceTypes = collect(LibrenmsConfig::get('device_types'))
            ->pluck('icon', 'type')
            ->map(fn ($icon) => "fas-$icon");

        return DB::table('devices')
            ->select('type', DB::raw('count(*) total'))
            ->groupBy('type')
            ->get()
            ->map(fn ($t) => Stat::make(Str::title($t->type ?: 'None'), $t->total)
                ->icon($deviceTypes->get($t->type))
                ->url(DeviceResource::getUrl('index', ['filters' => ['type' => ['value' => $t->type]]]))
            )
            ->all();
    }
}

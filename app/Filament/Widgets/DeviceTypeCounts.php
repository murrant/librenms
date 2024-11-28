<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeviceTypeCounts extends BaseWidget
{
    protected function getStats(): array
    {
        return DB::table('devices')
            ->select('type', DB::raw('count(*) total'))
            ->groupBy('type')
            ->get()
            ->map(fn($t) => Stat::make(Str::title($t->type ?: 'None'), $t->total))
            ->all();
    }
}

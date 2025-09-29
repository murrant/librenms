<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Filament\Resources\Devices\DeviceResource;
use App\Filament\Widgets\NetworkChart;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDevice extends ViewRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            NetworkChart::class,
        ];
    }
}

<?php

namespace App\Filament\Resources\Ports\Pages;

use App\Filament\Resources\Ports\PortResource;
use App\Filament\Widgets\Graph;
use App\Filament\Widgets\NetworkChart;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPort extends ViewRecord
{
    protected static string $resource = PortResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            Graph::class,
            NetworkChart::class,
        ];
    }
}

<?php

namespace App\Filament\Resources\Ports\Pages;

use App\Filament\Resources\Devices\DeviceResource;
use App\Filament\Resources\Ports\PortResource;
use App\Filament\Widgets\Graph;
use App\Filament\Widgets\NetworkChart;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewPort extends ViewRecord
{
    protected static string $resource = PortResource::class;

    public function getTitle(): string
    {
        return $this->record->getFullLabel();
    }

    public function getSubheading(): string|HtmlString
    {
        return new HtmlString('<a href="' . DeviceResource::getUrl('view', ['record' => $this->record->device]) . '">' . e($this->record->device->displayName()) . '</a>');
    }

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

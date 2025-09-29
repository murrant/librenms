<?php

namespace App\Filament\Resources\Ports\Pages;

use App\Filament\Resources\Ports\PortResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePort extends CreateRecord
{
    protected static string $resource = PortResource::class;
}

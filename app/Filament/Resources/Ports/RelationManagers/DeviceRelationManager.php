<?php

namespace App\Filament\Resources\Ports\RelationManagers;

use App\Filament\Resources\Devices\DeviceResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class DeviceRelationManager extends RelationManager
{
    protected static string $relationship = 'device';

    protected static ?string $relatedResource = DeviceResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}

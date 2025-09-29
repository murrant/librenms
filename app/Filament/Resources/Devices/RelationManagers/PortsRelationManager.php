<?php

namespace App\Filament\Resources\Devices\RelationManagers;

use App\Filament\Resources\Ports\PortResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class PortsRelationManager extends RelationManager
{
    protected static string $relationship = 'ports';

    protected static ?string $relatedResource = PortResource::class;

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

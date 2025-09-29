<?php

namespace App\Filament\Resources\Devices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('inserted')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('hostname')
                    ->searchable(),
                TextColumn::make('sysName')
                    ->searchable(),
                TextColumn::make('display')
                    ->searchable(),
                TextColumn::make('ip'),
                TextColumn::make('overwrite_ip')
                    ->searchable(),
                TextColumn::make('community')
                    ->searchable(),
                TextColumn::make('authlevel')
                    ->badge(),
                TextColumn::make('authname')
                    ->searchable(),
                TextColumn::make('authpass')
                    ->searchable(),
                TextColumn::make('authalgo')
                    ->searchable(),
                TextColumn::make('cryptopass')
                    ->searchable(),
                TextColumn::make('cryptoalgo')
                    ->searchable(),
                TextColumn::make('snmpver')
                    ->searchable(),
                TextColumn::make('port')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('transport')
                    ->searchable(),
                TextColumn::make('timeout')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('retries')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('snmp_disable')
                    ->boolean(),
                TextColumn::make('bgpLocalAs')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sysObjectID')
                    ->searchable(),
                TextColumn::make('location.id')
                    ->searchable(),
                TextColumn::make('os')
                    ->searchable(),
                IconColumn::make('status')
                    ->boolean(),
                TextColumn::make('status_reason')
                    ->searchable(),
                IconColumn::make('ignore')
                    ->boolean(),
                IconColumn::make('disabled')
                    ->boolean(),
                TextColumn::make('uptime')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('agent_uptime')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_polled')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_poll_attempted')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_polled_timetaken')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_discovered_timetaken')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_discovered')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_ping')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_ping_timetaken')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('icon')
                    ->searchable(),
                TextColumn::make('poller_group')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('override_sysLocation')
                    ->boolean(),
                TextColumn::make('port_association_mode')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_depth')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('disable_notify')
                    ->boolean(),
                IconColumn::make('ignore_status')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

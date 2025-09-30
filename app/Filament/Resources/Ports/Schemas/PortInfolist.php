<?php

namespace App\Filament\Resources\Ports\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use LibreNMS\Util\Number;

class PortInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Overview')
                    ->description('Status and key characteristics of this port')
                    ->columns(3)
                    ->schema([
                        Group::make([
                            TextEntry::make('ifName')
                                ->label('Interface')
                                ->icon('heroicon-o-cpu-chip')
                                ->extraAttributes(['class' => 'text-lg font-semibold'])
                                ->placeholder('-'),
                            TextEntry::make('ifAlias')
                                ->label('Description')
                                ->placeholder('-'),
                        ])
                            ->columns(1)
                            ->columnSpan(2),

                        Group::make([
                            TextEntry::make('ifOperStatus')
                                ->label('Oper')
                                ->badge()
                                ->icon(fn ($state) => $state === 'up' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                                ->color(fn ($state) => match ($state) {
                                    'up' => 'success',
                                    'down' => 'danger',
                                    'testing' => 'warning',
                                    default => 'gray',
                                }),
                            TextEntry::make('ifAdminStatus')
                                ->label('Admin')
                                ->badge()
                                ->color(fn ($state) => $state === 'up' ? 'success' : ($state === 'down' ? 'danger' : 'gray')),
                            TextEntry::make('ifDuplex')
                                ->label('Duplex')
                                ->badge()
                                ->color(fn ($state) => $state === 'full' ? 'success' : ($state ? 'warning' : 'gray')),
                        ])
                            ->columns(1)
                            ->columnSpan(1),

                        Group::make([
                            TextEntry::make('ifSpeed')
                                ->label('Speed')
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state ? Number::formatSi($state, 2, 0, 'bps') : '-')
                                ->color('info'),
                            TextEntry::make('ifMtu')
                                ->label('MTU')
                                ->badge()
                                ->color('gray'),
                            TextEntry::make('ifPhysAddress')
                                ->label('MAC')
                                ->copyable()
                                ->copyMessage('MAC copied')
                                ->extraAttributes(['class' => 'whitespace-nowrap font-mono'])
                                ->placeholder('-'),
                        ])
                            ->columns(3)
                            ->columnSpan(3),
                    ]),

                Section::make('Network')
                    ->columns(3)
                    ->compact()
                    ->schema([
                        TextEntry::make('device.device_id')
                            ->label('Device ID')
                            ->placeholder('-'),
                        TextEntry::make('ifVlan')
                            ->label('VLAN')
                            ->badge()
                            ->color('info')
                            ->placeholder('-'),
                        TextEntry::make('ifTrunk')
                            ->label('Trunk Mode')
                            ->badge()
                            ->color('gray')
                            ->placeholder('-'),
                        TextEntry::make('ifVrf')
                            ->label('VRF')
                            ->placeholder('-'),
                        TextEntry::make('ifType')
                            ->label('Type')
                            ->placeholder('-'),
                        TextEntry::make('ifIndex')
                            ->label('Index')
                            ->placeholder('-'),
                    ]),

                Section::make('Traffic')
                    ->description('Recent traffic and packet rates')
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 4,
                    ])
                    ->schema([
                        Group::make([
                            TextEntry::make('ifInOctets_rate')
                                ->label('Inbound')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->badge()
                                ->color('success')
                                ->formatStateUsing(fn ($state) => $state !== null ? Number::formatSi($state * 8, 2, 0, 'bps') : '-'),
                            TextEntry::make('ifOutOctets_rate')
                                ->label('Outbound')
                                ->icon('heroicon-o-arrow-up-tray')
                                ->badge()
                                ->color('info')
                                ->formatStateUsing(fn ($state) => $state !== null ? Number::formatSi($state * 8, 2, 0, 'bps') : '-'),
                        ])
                            ->columns(2)
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 2,
                            ]),
                        Group::make([
                            TextEntry::make('ifInUcastPkts_rate')
                                ->label('In pps')
                                ->badge()
                                ->color('success')
                                ->formatStateUsing(fn ($state) => $state !== null ? Number::formatSi($state, 2, 0, '') : '-')
                                ->helperText('packets/s'),
                            TextEntry::make('ifOutUcastPkts_rate')
                                ->label('Out pps')
                                ->badge()
                                ->color('info')
                                ->formatStateUsing(fn ($state) => $state !== null ? Number::formatSi($state, 2, 0, '') : '-')
                                ->helperText('packets/s'),
                        ])
                            ->columns(2)
                            ->columnSpan(1),
                        Group::make([
                            TextEntry::make('ifInErrors_rate')
                                ->label('In Errors')
                                ->badge()
                                ->color(fn ($state) => self::errorColor($state))
                                ->formatStateUsing(fn ($state) => $state !== null ? Number::formatSi($state, 2, 0, '') : '-')
                                ->helperText('errors/s'),
                            TextEntry::make('ifOutErrors_rate')
                                ->label('Out Errors')
                                ->badge()
                                ->color(fn ($state) => self::errorColor($state))
                                ->formatStateUsing(fn ($state) => $state !== null ? Number::formatSi($state, 2, 0, '') : '-')
                                ->helperText('errors/s'),
                        ])
                            ->columns(2)
                            ->columnSpan(1),
                    ]),

                Section::make('Administrative')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('port_descr_type')->label('Type')->placeholder('-'),
                        TextEntry::make('port_descr_descr')->label('Description')->placeholder('-'),
                        TextEntry::make('port_descr_circuit')->label('Circuit')->placeholder('-'),
                        TextEntry::make('port_descr_speed')->label('Speed (desc)')->placeholder('-'),
                        TextEntry::make('port_descr_notes')->label('Notes')->placeholder('-'),
                        TextEntry::make('ifLastChange')->label('Last Change')->placeholder('-'),
                        TextEntry::make('combined_state')
                            ->label('State')
                            ->state(function ($record) {
                                if ($record->deleted ?? false) {
                                    return 'Deleted';
                                }
                                if ($record->disabled ?? false) {
                                    return 'Disabled';
                                }
                                if ($record->ignore ?? false) {
                                    return 'Ignored';
                                }
                                return null; // Active: show nothing
                            })
                            ->hidden(fn ($record) => ! (($record->deleted ?? false) || ($record->disabled ?? false) || ($record->ignore ?? false)))
                            ->badge()
                            ->color(function ($state) {
                                return match ($state) {
                                    'Deleted' => 'danger',
                                    'Disabled' => 'warning',
                                    'Ignored' => 'gray',
                                    default => 'gray',
                                };
                            }),
                    ]),

                Section::make('Advanced')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('ifSpeed_prev')->label('Prev Speed')->placeholder('-'),
                            TextEntry::make('ifOperStatus_prev')->label('Prev Oper')->placeholder('-'),
                            TextEntry::make('ifAdminStatus_prev')->label('Prev Admin')->placeholder('-'),
                            TextEntry::make('pagpOperationMode')->placeholder('-'),
                            TextEntry::make('pagpPortState')->placeholder('-'),
                            TextEntry::make('pagpPartnerDeviceId')->placeholder('-'),
                            TextEntry::make('pagpPartnerLearnMethod')->placeholder('-'),
                            TextEntry::make('pagpPartnerIfIndex')->placeholder('-'),
                            TextEntry::make('pagpPartnerGroupIfIndex')->placeholder('-'),
                            TextEntry::make('pagpPartnerDeviceName')->placeholder('-'),
                            TextEntry::make('pagpEthcOperationMode')->placeholder('-'),
                            TextEntry::make('pagpDeviceId')->placeholder('-'),
                            TextEntry::make('pagpGroupIfIndex')->placeholder('-'),
                            TextEntry::make('ifInUcastPkts')->placeholder('-'),
                            TextEntry::make('ifInUcastPkts_prev')->placeholder('-'),
                            TextEntry::make('ifInUcastPkts_delta')->placeholder('-'),
                            TextEntry::make('ifOutUcastPkts')->placeholder('-'),
                            TextEntry::make('ifOutUcastPkts_prev')->placeholder('-'),
                            TextEntry::make('ifOutUcastPkts_delta')->placeholder('-'),
                            TextEntry::make('ifInErrors')->placeholder('-'),
                            TextEntry::make('ifInErrors_prev')->placeholder('-'),
                            TextEntry::make('ifInErrors_delta')->placeholder('-'),
                            TextEntry::make('ifOutErrors')->placeholder('-'),
                            TextEntry::make('ifOutErrors_prev')->placeholder('-'),
                            TextEntry::make('ifOutErrors_delta')->placeholder('-'),
                            TextEntry::make('ifInOctets')->placeholder('-'),
                            TextEntry::make('ifInOctets_prev')->placeholder('-'),
                            TextEntry::make('ifInOctets_delta')->placeholder('-'),
                            TextEntry::make('ifOutOctets')->placeholder('-'),
                            TextEntry::make('ifOutOctets_prev')->placeholder('-'),
                            TextEntry::make('ifOutOctets_delta')->placeholder('-'),
                            TextEntry::make('poll_time')->placeholder('-'),
                            TextEntry::make('poll_prev')->placeholder('-'),
                            TextEntry::make('poll_period')->placeholder('-'),
                        ]),
                    ]),
            ]);
    }

    protected static function errorColor($rate): string
    {
        if (! is_numeric($rate) || $rate <= 0) {
            return 'gray';
        }
        if ($rate < 1) {
            return 'warning';
        }
        return 'danger';
    }
}

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
                // Primary port identification and status
                Section::make('Interface Overview')
                    ->description('Basic interface information and operational status')
                    ->columns(4)
                    ->schema([
                        Group::make([
                            TextEntry::make('ifName')
                                ->label('Interface')
                                ->icon('fas-link')
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
                                ->label('Operational')
                                ->badge()
                                ->icon(fn ($state) => $state === 'up' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                                ->color(fn ($state) => match ($state) {
                                    'up' => 'success',
                                    'down' => 'danger',
                                    'testing' => 'warning',
                                    default => 'gray',
                                }),
                            TextEntry::make('ifAdminStatus')
                                ->label('Administrative')
                                ->badge()
                                ->color(fn ($state) => $state === 'up' ? 'success' : ($state === 'down' ? 'danger' : 'gray')),
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
                                    return null;
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
                        ])
                            ->columns(1)
                            ->columnSpan(1),

                        Group::make([
                            TextEntry::make('ifIndex')
                                ->label('Index')
                                ->placeholder('-'),
                            TextEntry::make('ifLastChange')
                                ->label('Last Change')
                                ->placeholder('-'),
                        ])
                            ->columns(1)
                            ->columnSpan(1),
                    ]),

                // Network/Layer 2-3 configuration
                Section::make('Network Configuration')
                    ->description('VLAN, VRF, and IP addressing')
                    ->columns(4)
                    ->schema([
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
                            ->placeholder('-')
                            ->columnSpan(2),

                        TextEntry::make('ipv4.ipv4_address')
                            ->label('IPv4 Address')
                            ->placeholder('-'),

                        TextEntry::make('ipv6.ipv6_address')
                            ->label('IPv6 Address')
                            ->placeholder('-')
                            ->columnSpan(3),
                    ])

,

                // Physical and link characteristics
                Section::make('Physical Layer')
                    ->description('Speed, duplex, MTU, and MAC address')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('ifSpeed')
                            ->label('Speed')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? Number::formatSi($state, 2, 0, 'bps') : '-')
                            ->color('info'),

                        TextEntry::make('ifDuplex')
                            ->label('Duplex')
                            ->badge()
                            ->color(fn ($state) => $state === 'full' ? 'success' : ($state ? 'warning' : 'gray')),

                        TextEntry::make('ifMtu')
                            ->label('MTU')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('ifType')
                            ->label('Type')
                            ->placeholder('-'),

                        TextEntry::make('ifPhysAddress')
                            ->label('MAC Address')
                            ->copyable()
                            ->copyMessage('MAC copied')
                            ->extraAttributes(['class' => 'whitespace-nowrap font-mono'])
                            ->placeholder('-'),
                    ]),

                // Real-time traffic metrics
                Section::make('Traffic Statistics')
                    ->description('Current bandwidth utilization and packet rates')
                    ->columns(3)
                    ->schema([
                        Group::make([
                            TextEntry::make('ifInOctets_rate')
                                ->label('Inbound Rate')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->badge()
                                ->color('success')
                                ->formatStateUsing(fn ($state) => $state !== null ? Number::formatSi($state * 8, 2, 0, 'bps') : '-'),
                            TextEntry::make('ifInUcastPkts_rate')
                                ->label('In Packets/s')
                                ->badge()
                                ->color('success')
                                ->formatStateUsing(fn ($state) => $state !== null ? Number::formatSi($state, 2, 0, '') : '-'),
                        ])
                            ->columns(1)
                            ->columnSpan(1),

                        Group::make([
                            TextEntry::make('ifOutOctets_rate')
                                ->label('Outbound Rate')
                                ->icon('heroicon-o-arrow-up-tray')
                                ->badge()
                                ->color('info')
                                ->formatStateUsing(fn ($state) => $state !== null ? Number::formatSi($state * 8, 2, 0, 'bps') : '-'),
                            TextEntry::make('ifOutUcastPkts_rate')
                                ->label('Out Packets/s')
                                ->badge()
                                ->color('info')
                                ->formatStateUsing(fn ($state) => $state !== null ? Number::formatSi($state, 2, 0, '') : '-'),
                        ])
                            ->columns(1)
                            ->columnSpan(1),

                        Group::make([
                            TextEntry::make('ifInErrors_rate')
                                ->label('In Errors/s')
                                ->badge()
                                ->color(fn ($state) => self::errorColor($state))
                                ->formatStateUsing(fn ($state) => $state !== null ? Number::formatSi($state, 2, 0, '') : '-'),
                            TextEntry::make('ifOutErrors_rate')
                                ->label('Out Errors/s')
                                ->badge()
                                ->color(fn ($state) => self::errorColor($state))
                                ->formatStateUsing(fn ($state) => $state !== null ? Number::formatSi($state, 2, 0, '') : '-'),
                        ])
                            ->columns(1)
                            ->columnSpan(1),
                    ]),

                // Administrative metadata
                Section::make('Administrative Details')
                    ->description('Custom descriptions and operational notes')
                    ->columns(3)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('port_descr_type')
                            ->label('Port Type')
                            ->placeholder('-'),
                        TextEntry::make('port_descr_descr')
                            ->label('Description')
                            ->placeholder('-'),
                        TextEntry::make('port_descr_speed')
                            ->label('Documented Speed')
                            ->placeholder('-'),
                        TextEntry::make('port_descr_circuit')
                            ->label('Circuit ID')
                            ->placeholder('-'),
                        TextEntry::make('port_descr_notes')
                            ->label('Notes')
                            ->columnSpan(2)
                            ->placeholder('-'),
                    ]),

                // Advanced/debugging information
                Section::make('Advanced Details')
                    ->description('Historical data, deltas, and protocol-specific information')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            // Previous state tracking
                            TextEntry::make('ifSpeed_prev')->label('Previous Speed')->placeholder('-'),
                            TextEntry::make('ifOperStatus_prev')->label('Previous Oper Status')->placeholder('-'),
                            TextEntry::make('ifAdminStatus_prev')->label('Previous Admin Status')->placeholder('-'),

                            // PAgP (Port Aggregation Protocol) fields
                            TextEntry::make('pagpOperationMode')->label('PAgP Operation Mode')->placeholder('-'),
                            TextEntry::make('pagpPortState')->label('PAgP Port State')->placeholder('-'),
                            TextEntry::make('pagpEthcOperationMode')->label('PAgP EthC Mode')->placeholder('-'),
                            TextEntry::make('pagpDeviceId')->label('PAgP Device ID')->placeholder('-'),
                            TextEntry::make('pagpGroupIfIndex')->label('PAgP Group Index')->placeholder('-'),
                            TextEntry::make('pagpPartnerDeviceId')->label('PAgP Partner Device')->placeholder('-'),
                            TextEntry::make('pagpPartnerDeviceName')->label('PAgP Partner Name')->placeholder('-'),
                            TextEntry::make('pagpPartnerIfIndex')->label('PAgP Partner Index')->placeholder('-'),
                            TextEntry::make('pagpPartnerGroupIfIndex')->label('PAgP Partner Group')->placeholder('-'),
                            TextEntry::make('pagpPartnerLearnMethod')->label('PAgP Learn Method')->placeholder('-'),

                            // Unicast packets
                            TextEntry::make('ifInUcastPkts')->label('In Unicast Pkts')->placeholder('-'),
                            TextEntry::make('ifInUcastPkts_prev')->label('In Unicast Prev')->placeholder('-'),
                            TextEntry::make('ifInUcastPkts_delta')->label('In Unicast Delta')->placeholder('-'),
                            TextEntry::make('ifOutUcastPkts')->label('Out Unicast Pkts')->placeholder('-'),
                            TextEntry::make('ifOutUcastPkts_prev')->label('Out Unicast Prev')->placeholder('-'),
                            TextEntry::make('ifOutUcastPkts_delta')->label('Out Unicast Delta')->placeholder('-'),

                            // Errors
                            TextEntry::make('ifInErrors')->label('In Errors')->placeholder('-'),
                            TextEntry::make('ifInErrors_prev')->label('In Errors Prev')->placeholder('-'),
                            TextEntry::make('ifInErrors_delta')->label('In Errors Delta')->placeholder('-'),
                            TextEntry::make('ifOutErrors')->label('Out Errors')->placeholder('-'),
                            TextEntry::make('ifOutErrors_prev')->label('Out Errors Prev')->placeholder('-'),
                            TextEntry::make('ifOutErrors_delta')->label('Out Errors Delta')->placeholder('-'),

                            // Octets/bytes
                            TextEntry::make('ifInOctets')->label('In Octets')->placeholder('-'),
                            TextEntry::make('ifInOctets_prev')->label('In Octets Prev')->placeholder('-'),
                            TextEntry::make('ifInOctets_delta')->label('In Octets Delta')->placeholder('-'),
                            TextEntry::make('ifOutOctets')->label('Out Octets')->placeholder('-'),
                            TextEntry::make('ifOutOctets_prev')->label('Out Octets Prev')->placeholder('-'),
                            TextEntry::make('ifOutOctets_delta')->label('Out Octets Delta')->placeholder('-'),

                            // Polling information
                            TextEntry::make('poll_time')->label('Poll Time')->placeholder('-'),
                            TextEntry::make('poll_prev')->label('Previous Poll')->placeholder('-'),
                            TextEntry::make('poll_period')->label('Poll Period')->placeholder('-'),
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

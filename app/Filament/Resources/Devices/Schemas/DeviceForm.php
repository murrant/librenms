<?php

namespace App\Filament\Resources\Devices\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('inserted'),
                TextInput::make('hostname')
                    ->required(),
                TextInput::make('sysName')
                    ->default(null),
                TextInput::make('display')
                    ->default(null),
                TextInput::make('ip')
                    ->default(null),
                TextInput::make('overwrite_ip')
                    ->default(null),
                TextInput::make('community')
                    ->default(null),
                Select::make('authlevel')
                    ->options(['noAuthNoPriv' => 'No auth no priv', 'authNoPriv' => 'Auth no priv', 'authPriv' => 'Auth priv'])
                    ->default(null),
                TextInput::make('authname')
                    ->default(null),
                TextInput::make('authpass')
                    ->default(null),
                TextInput::make('authalgo')
                    ->default(null),
                TextInput::make('cryptopass')
                    ->default(null),
                TextInput::make('cryptoalgo')
                    ->default(null),
                TextInput::make('snmpver')
                    ->required()
                    ->default('v2c'),
                TextInput::make('port')
                    ->required()
                    ->numeric()
                    ->default(161),
                TextInput::make('transport')
                    ->required()
                    ->default('udp'),
                TextInput::make('timeout')
                    ->numeric()
                    ->default(null),
                TextInput::make('retries')
                    ->numeric()
                    ->default(null),
                Toggle::make('snmp_disable')
                    ->required(),
                TextInput::make('bgpLocalAs')
                    ->numeric()
                    ->default(null),
                TextInput::make('sysObjectID')
                    ->default(null),
                Textarea::make('sysDescr')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('sysContact')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('version')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('hardware')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('features')
                    ->default(null)
                    ->columnSpanFull(),
                Select::make('location_id')
                    ->relationship('location', 'id')
                    ->default(null),
                TextInput::make('os')
                    ->default(null),
                Toggle::make('status')
                    ->required(),
                TextInput::make('status_reason')
                    ->required(),
                Toggle::make('ignore')
                    ->required(),
                Toggle::make('disabled')
                    ->required(),
                TextInput::make('uptime')
                    ->numeric()
                    ->default(null),
                TextInput::make('agent_uptime')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('last_polled'),
                DateTimePicker::make('last_poll_attempted'),
                TextInput::make('last_polled_timetaken')
                    ->numeric()
                    ->default(null),
                TextInput::make('last_discovered_timetaken')
                    ->numeric()
                    ->default(null),
                DateTimePicker::make('last_discovered'),
                DateTimePicker::make('last_ping'),
                TextInput::make('last_ping_timetaken')
                    ->numeric()
                    ->default(null),
                Textarea::make('purpose')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('type')
                    ->required()
                    ->default(''),
                Textarea::make('serial')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('icon')
                    ->default(null),
                TextInput::make('poller_group')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('override_sysLocation'),
                Textarea::make('notes')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('port_association_mode')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('max_depth')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('disable_notify')
                    ->required(),
                Toggle::make('ignore_status')
                    ->required(),
            ]);
    }
}

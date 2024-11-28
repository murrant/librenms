<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Date;
use LibreNMS\Data\Source\Rrd;

class Graph extends ChartWidget
{
    protected static ?string $heading = 'Graph';

    protected function getData(): array
    {
        $port_id = 294;
        $hostname = 'palmer.rtr.ncn.net';
        $rrd = \App\Facades\Rrd::name($hostname, \App\Facades\Rrd::portName($port_id));
        $data = Rrd::make()
            ->def('in_oct', 'INOCTETS', $rrd)
            ->def('out_oct', 'OUTOCTETS', $rrd)
            ->cdef('in_bits', 'in_oct,8,*')
            ->cdef('out_bits', 'out_oct,8,*')
            ->xport(['in_bits', 'out_bits'])
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'In Bits/s',
                    'data' => $data['data']['in_bits'],
                ],
                [
                    'label' => 'Out Bits/s',
                    'data' => $data['data']['out_bits'],
                ]
            ],
            'labels' => array_map(fn($timestamp) => Date::createFromTimestamp($timestamp)->toDateTimeString(), $data['timestamps']),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 3,
    ];
}

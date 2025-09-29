<?php

namespace App\Filament\Widgets;

use App\Models\Device;
use Filament\Widgets\ChartWidget;
use LibreNMS\Data\ChartDataset;
use LibreNMS\Data\Source\RrdCommand;

class Graph extends ChartWidget
{
    protected ?string $heading = 'Graph';

    public ?Device $record = null;

    protected ?array $options = [
        'scales' => [
            'x' => [
                'type' => 'timeseries',
            ],
        ],
    ];

    protected function getData(): array
    {
        try {
            $port_id = $this->record->port_id ?? 294;
            $device_id = $this->record->device_id ?? 9;
            $hostname = Device::whereDeviceId($device_id)->value('hostname');
            $rrd = \App\Facades\Rrd::name($hostname, \App\Facades\Rrd::portName($port_id));
            $data = RrdCommand::make(time() - 86400)
                ->def('in_oct', 'INOCTETS', $rrd)
                ->def('out_oct', 'OUTOCTETS', $rrd)
                ->cdef('in_bits', 'in_oct,8,*')
                ->cdef('out_bits', 'out_oct,8,*')
                ->xport(['in_bits', 'out_bits']);

            return $data->forChartJs([
                new ChartDataset('in_bits', 'In Bits/s', '#608720', '#90B04055'),
                new ChartDataset('out_bits', 'Out Bits/s', '#606090', '#8080C055'),
            ]);
        } catch (\Throwable $e) {
            // Log for debugging without breaking the widget rendering
            if (function_exists('logger')) {
                logger()->warning('Graph widget failed to load data', ['exception' => $e]);
            }

            return [
                'datasets' => [
                    [
                        'label' => 'No Data',
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }
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

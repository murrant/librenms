<?php

namespace App\Filament\Widgets;

use App\Models\Device;
use App\Models\Port;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;
use LibreNMS\Data\ChartDataset;
use LibreNMS\Data\Source\RrdCommand;

class Graph extends ChartWidget
{
    protected ?string $heading = 'Graph';

    public ?Model $record = null;

    protected ?array $options = [
        'scales' => [
            'x' => [
                'type' => 'timeseries',
            ],
        ],
    ];

    private function getPort(): Port
    {
        if ($this->record instanceof Port) {
            return $this->record;
        }

        if ($this->record instanceof Device) {
            return $this->record->ports()->first();
        }

        return Port::first();
    }

    protected function getData(): array
    {
        try {
            $port = $this->getPort();
            $hostname = $port->device->hostname;

            $rrd = \App\Facades\Rrd::name($hostname, \App\Facades\Rrd::portName($port->port_id));
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

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 3,
    ];
}

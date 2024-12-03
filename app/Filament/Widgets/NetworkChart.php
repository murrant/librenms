<?php

namespace App\Filament\Widgets;

use App\Models\Device;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use LibreNMS\Data\ChartDataset;
use LibreNMS\Data\Source\RrdCommand;
use LibreNMS\Util\Time;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;

class NetworkChart extends ApexChartWidget
{
    /**
     * Chart Id
     *
     * @var string
     */
    protected static ?string $chartId = 'networkChart';

    protected static ?string $pollingInterval = '10s';

    protected static $timeRanges = [
        'Last 6 Hours' => ['-6h', null],
        'Last Day' => ['-1d', null],
        'Last 2 Days' => ['-2d', null],
        'Last 7 Days' => ['-7d', null],
        'Last 30 Days' => ['-30d', null],
        'Last Year' => ['-1y', null],
    ];

    /**
     * Widget Title
     *
     * @var string|null
     */
    protected static ?string $heading = 'NetworkChart';

    protected function getFormSchema(): array
    {
        return [

            TextInput::make('title')
                ->default('NetworkChart'),
//                ->reactive(),

            DateRangePicker::make('timerange')
                ->ranges(self::$timeRanges)
                ->useRangeLabels()
                ->default('Last Day'),

        ];
    }

    protected function getHeading(): ?string
    {
        return $this->filterFormData['title'];
    }

    /**
     * Chart options (series, labels, types, size, animations...)
     * https://apexcharts.com/docs/options
     *
     * @return array
     */
    protected function getOptions(): array
    {
        $timerange = $this->filterFormData['timerange'];
        [$dateStart, $dateEnd] = is_array($timerange) ? $timerange : self::$timeRanges[$timerange];

        $port_id = 294;
        $device_id = 9;
        $hostname = Device::whereDeviceId($device_id)->value('hostname');
        $rrd_file = \App\Facades\Rrd::name($hostname, \App\Facades\Rrd::portName($port_id));

        $rrd_command = RrdCommand::make(Time::parseAt($dateStart))
            ->def('in_oct', 'INOCTETS', $rrd_file)
            ->def('out_oct', 'OUTOCTETS', $rrd_file)
            ->cdef('in_bits', 'in_oct,8,*')
            ->cdef('out_bits', 'out_oct,8,*');

        if ($dateEnd) {
            $rrd_command->end = Time::parseAt($dateEnd);
        }

        $series = $rrd_command->xport(['in_bits', 'out_bits'])->forApexCharts([
            new ChartDataset('in_bits', 'In Bits/s', '#82b52d'),
            new ChartDataset('out_bits', 'Out Bits/s', '#8989c9'),
        ]);

        return [
            'chart' => [
                'type' => 'area',
                'height' => 300,
            ],
            'series' => $series,
            'xaxis' => [
                'type' => 'datetime',
//                'range' => 'XAXISRANGE',
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
//            'colors' => ['#82b52d', '#8989c9'],
            'stroke' => [
                'curve' => 'smooth',
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
        ];
    }

    protected function extraJsOptions(): ?\Filament\Support\RawJs
    {
        return RawJs::make(<<<'JS'
        {
            yaxis: {
                labels: {
                    formatter: function (val, index) {
                        let i = -1;
                        const byteUnits = [
                          " kbps",
                          " Mbps",
                          " Gbps",
                          " Tbps",
                          " Pbps",
                          " Ebps",
                          " Zbps",
                          " Ybps"
                        ];
                        do {
                          val = val / 1024;
                          i++;
                        } while (val > 1024);

                        return Math.max(val, 0.1).toFixed(1) + byteUnits[i];
                    }
                }
            }
        }
        JS);
    }
}

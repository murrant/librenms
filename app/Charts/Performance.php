<?php

namespace App\Charts;

use App\Models\Device;
use ConsoleTVs\Charts\Classes\Chartjs\Chart;
use ConsoleTVs\Charts\Classes\Chartjs\Dataset;

class Performance extends Chart
{
    /**
     * Initializes the chart.
     *
     * @param Device $device
     */
    public function __construct(Device $device)
    {
        parent::__construct();

        $data = $device->perf()->orderBy('timestamp')->get();

        $this->labels(['Date', 'Average', 'Minimum', 'Maximum', 'Loss']);
        $this->dataset('date', 'line', $data->pluck('timestamp'));
        $this->dataset('avg', 'line', $data->pluck('avg'));
        $this->dataset('min', 'line', $data->pluck('min'));
        $this->dataset('max', 'line', $data->pluck('max'));
        $this->dataset('loss', 'line', $data->pluck('loss'));

        $this->options([
            'responsive' => true,
            'title' => [
                'display' => true,
                'text' => 'Performance for ' . $device->hostname
            ],
            'tooltips' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'hover' => [
                'mode' => 'nearest',
                'intersect' => true
            ],
            'scales' => [
                'xAxes' => [
                    'type' => 'time',
                    'distribution' => 'series',
                    'ticks' => [
                        'source' => 'labels'
                    ]
                ],
                'yAxes' => [
                    'scaleLabel' => [
                        'display' => true,
                        'labelString' => 'Latency (ms)'
                    ]
                ]
            ]
        ]);

//        scales: {
//        xAxes: [{
//            type: 'time',
//						distribution: 'series',
//						ticks: {
//                source: 'labels'
//						}
//					}],
//					yAxes: [{
//            scaleLabel: {
//                display: true,
//							labelString: 'Closing price ($)'
//						}
//        }]
    }

    public function container(string $container = null)
    {
        return '<div id="graphdiv"></div>';
    }

    public function script(string $script = null)
    {
        return '<script type="text/javascript">
  g = new Dygraph(
    document.getElementById("graphdiv"),
"' .
            $this->formatLabels() . '\n' .
            $this->formatDatasets()
            . '"
    );
</script>';
    }

    public function formatLabels()
    {
        return implode(',', $this->labels);
    }

    public function formatDatasets()
    {

        $count = min(array_map(function ($dataset) {
            /** @var Dataset $dataset */
            return count($dataset->values);
        }, $this->datasets));

        $out = '';
        for ($x = 0; $x < $count; $x++) {
            $out .= implode(',', array_map(function ($dataset) use ($x) {
                    /** @var Dataset $dataset */
                    return $dataset->values[$x];
                }, $this->datasets)) . '\n';
        }

        return $out;
    }
}

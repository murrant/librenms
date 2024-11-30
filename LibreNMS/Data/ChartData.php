<?php

namespace LibreNMS\Data;

class ChartData
{
    protected function __construct(
        protected string $format,
        protected array $data,
        protected array $map = [],
    ) {
    }

    public static function createFromRrd(array $legend, array $data): static
    {
        $map = ['_timestamp' => 0];
        // RRD legend is shifted by one when timestamp is included
        foreach ($legend as $index => $name) {
            $map[$name] = $index + 1;
        }

        return new static('grouped_by_point', $data, $map);
    }

    /**
     * @param  ChartDataset[]  $chartDatasets
     * @return array
     */
    public function forChartJs(array $chartDatasets): array
    {
        $data = [];

        if ($this->format == 'grouped_by_point') {
            $timestamp_index = $this->map['_timestamp'];
            $ds = array_diff_key($this->map, ['_timestamp' => null]);

            foreach ($this->data as $point) {
                foreach ($ds as $name => $index) {
                    $data[$name][] = ['x' => (int) $point[$timestamp_index], 'y' => $point[$index]];
                }
            }
        }

        $datasets = [];
        foreach ($chartDatasets as $ds) {
            $datasets[] = [
                'label' => $ds->label,
                'data' => $data[$ds->name],
                'fill' => true,
                'borderColor' => $ds->color,
                'backgroundColor' => $ds->fill,
            ];
        }

        return [
            'datasets' => $datasets,
        ];
    }
}

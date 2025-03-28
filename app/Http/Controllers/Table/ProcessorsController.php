<?php

namespace App\Http\Controllers\Table;

use App\Models\Processor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use LibreNMS\Util\Html;
use LibreNMS\Util\Url;

class ProcessorsController extends TableController
{
    protected $default_sort = ['device_hostname' => 'asc', 'processor_descr' => 'asc'];

    protected function sortFields($request): array
    {
        return [
            'device_hostname',
            'processor_descr',
            'processor_usage',
        ];
    }

    protected function searchFields(Request $request): array
    {
        return [
            'device_hostname',
            'processor_descr'
        ];
    }

    /**
     * @inheritDoc
     */
    protected function baseQuery(Request $request): Builder
    {
        return Processor::query()
            ->hasAccess($request->user())
            ->with(['device', 'device.location'])
            ->withAggregate('device', 'hostname');
    }

    /**
     * @param Processor $processor
     */
    public function formatItem($processor): array
    {
        if (\Request::input('view') == 'graphs') {
            return array_combine([
                'device_hostname',
                'processor_descr',
                'graph',
                'processor_usage',
            ], Html::graphRow([
                'type' => 'processor_usage',
                'id' => $processor->processor_id,
                'height' => 100,
                'width' => 216,
            ]));
        }

        return [
            'device_hostname' => Blade::render('<x-device-link :device="$device" />', ['device' => $processor->device]),
            'processor_descr' => $processor->processor_descr,
            'graph' => $this->miniGraph($processor),
            'processor_usage' => $this->usageBar($processor),
        ];
    }

    private function miniGraph(Processor $processor): string
    {
        return Url::graphPopup([
            'type' => 'processor_usage',
            'popup_title' => htmlentities(strip_tags($processor->device->displayName() . ': ' . $processor->processor_descr)),
            'id' => $processor->processor_id,
            'from' => '-1d',
            'height' => 20,
            'width' => 80,
        ]);
    }

    private function usageBar(Processor $processor): string
    {
        $perc = round($processor->processor_usage);
        $bar = Html::percentageBar(400, 20, $perc, $perc . '%', (100 - $perc) . '%', $processor->processor_perc_warn);

        return Url::graphPopup([
            'type' => 'processor_usage',
            'popup_title' => htmlentities(strip_tags($processor->device->displayName() . ': ' . $processor->processor_descr)),
            'id' => $processor->processor_id,
            'from' => '-1d',
            'height' => 20,
            'width' => 80,
        ], $bar);
    }
}

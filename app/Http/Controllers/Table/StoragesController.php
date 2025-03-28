<?php

namespace App\Http\Controllers\Table;

use App\Models\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use LibreNMS\Util\Html;
use LibreNMS\Util\Number;
use LibreNMS\Util\Url;

class StoragesController extends TableController
{
    protected $default_sort = ['device_hostname' => 'asc', 'storage_descr' => 'asc'];

    protected function sortFields($request): array
    {
        return [
            'device_hostname',
            'storage_descr',
            'storage_used',
            'storage_perc',
        ];
    }

    protected function searchFields(Request $request): array
    {
        return [
            'device_hostname',
            'storage_descr'
        ];
    }

    protected function baseQuery(Request $request): Builder
    {
        return Storage::query()
            ->hasAccess($request->user())
            ->with(['device', 'device.location'])
            ->withAggregate('device', 'hostname');
    }

    /**
     * @param Storage $storage
     */
    public function formatItem($storage): array
    {
        if (\Request::input('view') == 'graphs') {
            $row = Html::graphRow([
                'type' => 'storage_usage',
                'id' => $storage->storage_id,
                'height' => 100,
                'width' => 216,
            ]);

            return [
                'device_hostname' => $row[0],
                'storage_descr' => $row[1],
                'graph' => $row[2],
                'storage_used' => $row[3],
                'storage_perc' => '',
            ];
        }

        return [
            'device_hostname' => Blade::render('<x-device-link :device="$device" />', ['device' => $storage->device]),
            'storage_descr' => $storage->storage_descr,
            'graph' => $this->miniGraph($storage),
            'storage_used' => $this->usageBar($storage),
            'storage_perc' => round($storage->storage_perc) . '%',
        ];
    }

    private function miniGraph(Storage $storage): string
    {
        return Url::graphPopup([
            'type' => 'storage_usage',
            'popup_title' => htmlentities(strip_tags($storage->device->displayName() . ': ' . $storage->storage_descr)),
            'id' => $storage->storage_id,
            'from' => '-1d',
            'height' => 20,
            'width' => 80,
        ]);
    }

    private function usageBar(Storage $storage): string
    {
        $left_text = Number::formatBi($storage->storage_used) . ' / ' . Number::formatBi($storage->storage_size);
        $right_text = Number::formatBi($storage->storage_free);
        $bar = Html::percentageBar(400, 20, $storage->storage_perc, $left_text, $right_text, $storage->storage_perc_warn);

        return Url::graphPopup([
            'type' => 'storage_usage',
            'popup_title' => htmlentities(strip_tags($storage->device->displayName() . ': ' . $storage->storage_descr)),
            'id' => $storage->storage_id,
            'from' => '-1d',
            'height' => 20,
            'width' => 80,
        ], $bar);
    }

}

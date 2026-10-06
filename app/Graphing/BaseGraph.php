<?php

namespace App\Graphing;

use App\Facades\DeviceCache;
use App\Graphing\Contracts\Graph;
use App\Graphing\Exceptions\GraphSubjectNotFound;
use App\Models\Device;

/**
 * Sensible defaults for graph classes
 */
abstract class BaseGraph implements Graph
{
    public function subject(GraphQuery $query): GraphSubject
    {
        return new GraphSubject($this->device($query));
    }

    public function ability(): string
    {
        return 'view';
    }

    public function title(GraphSubject $subject): string
    {
        return $subject->device->display ?? '';
    }

    public function rules(): array
    {
        return [];
    }

    /**
     * The device from the device var, or from id for device graphs
     *
     * @throws GraphSubjectNotFound
     */
    protected function device(GraphQuery $query): Device
    {
        $deviceId = $query->deviceId ?? ($query->type === 'device' && count($query->ids) === 1 ? $query->ids[0] : null);
        $device = $deviceId ? DeviceCache::get($deviceId) : null;

        if (! $device?->exists) {
            throw new GraphSubjectNotFound('Device not found');
        }

        return $device;
    }
}

<?php

namespace App\Data\Polling;

use App\Models\Device;
use App\Models\DevicePollingMethod;
use App\Models\Secret;
use LibreNMS\Enum\PollingMethodType;

class PollingMethodService
{
    public function create(Device $device, PollingMethodType $type, array $settings, ?Secret $secret): DevicePollingMethod
    {
        $method = app($type->methodClass());

        $row = new DevicePollingMethod([
            'device_id' => $device->device_id,
            'method_type' => $type,
            'enabled' => true,
            'affects_availability' => (bool) ($method->getDefaults()['affects_availability'] ?? false),
            'secret_id' => $secret?->id,
            'settings' => $this->buildSettings($method, $settings),
        ]);

        $device->pollingMethods()->save($row);

        return $row;
    }

    public function update(DevicePollingMethod $row, array $validated, PollingMethodType $type): void
    {
        $method = app($type->methodClass());

        $row->enabled = (bool) ($validated['enabled'] ?? true);
        $row->affects_availability = (bool) ($validated['affects_availability'] ?? false);

        $row->settings = $this->mergeSettings($row->settings ?? [], $validated['settings'] ?? [], $method);

        $row->save();

        if ($row->wasChanged('enabled')) {
            $row->syncDeviceStatus();
        }
    }

    private function buildSettings($method, array $validated): array
    {
        $schemaDefaults = collect($method->getSettingsSchema())
            ->mapWithKeys(fn ($field, $key) => [
                $key => $field['default'] ?? (isset($field['options']) ? array_key_first($field['options']) : null),
            ])
            ->filter();

        return array_merge(
            $schemaDefaults->all(),
            collect($method->getDefaults())->except('affects_availability')->all(),
            $validated
        );
    }

    private function mergeSettings(array $existing, array $validated, $method): array
    {
        $allowed = collect($method->getSettingsSchema())->keys();

        return array_merge(
            $existing,
            collect($validated)->only($allowed)->all()
        );
    }
}

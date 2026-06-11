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
        $methodClass = $type->methodClass();

        $row = new DevicePollingMethod([
            'device_id'            => $device->device_id,
            'method_type'          => $type,
            'enabled'              => true,
            'affects_availability' => (bool) ($methodClass::getDefaults()['affects_availability'] ?? false),
            'secret_id'            => $secret?->id,
            'settings'             => $this->buildSettings($methodClass, $settings),
        ]);

        $device->pollingMethods()->save($row);

        return $row;
    }

    public function update(DevicePollingMethod $row, array $validated, PollingMethodType $type): void
    {
        $methodClass = $type->methodClass();

        $row->enabled = (bool) ($validated['enabled'] ?? true);
        $row->affects_availability = (bool) ($validated['affects_availability'] ?? false);

        $row->settings = $this->mergeSettings($row->settings ?? [], $validated['settings'] ?? [], $methodClass);

        $row->save();

        if ($row->wasChanged('enabled')) {
            $row->syncDeviceStatus();
        }
    }

    private function buildSettings(string $methodClass, array $validated): array
    {
        $schemaDefaults = collect($methodClass::getSettingsSchema())
            ->mapWithKeys(fn ($field, $key) => [
                $key => $field['default'] ?? (isset($field['options']) ? array_key_first($field['options']) : null),
            ])
            ->filter();

        return array_merge(
            $schemaDefaults->all(),
            collect($methodClass::getDefaults())->except('affects_availability')->all(),
            $validated
        );
    }

    private function mergeSettings(array $existing, array $validated, string $methodClass): array
    {
        $allowed = collect($methodClass::getSettingsSchema())->keys();

        return array_merge(
            $existing,
            collect($validated)->only($allowed)->all()
        );
    }
}

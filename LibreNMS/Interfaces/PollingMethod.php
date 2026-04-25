<?php

namespace LibreNMS\Interfaces;

use App\Data\Polling\ProbeResult;
use App\Models\Device;

interface PollingMethod
{
    /** Actively probe reachability and credential validity for this device. */
    public function probe(Device $device): ProbeResult;

    /**
     * UI/form schema for device-specific settings.
     *
     * @return array<string, array{type: string, options?: array<string,string>, visible_if: array}>
     */
    public function getSettingsSchema(): array;

    /**
     * Defaults for polling method per-device settings
     *
     * @return array<string, mixed>
     */
    public function getDefaults(): array;

    /**
     * Validation rules for polling method per-device settings
     *
     * @return array<string, array|string>
     */
    public function getRules(): array;
}

<?php

namespace LibreNMS\Interfaces;

use App\Data\Polling\ProbeResult;
use App\Data\Secrets\SecretData;
use App\Models\Device;

interface PollingMethod
{
    /** Actively probe reachability and credential validity for this device. */
    public function probe(Device $device): ProbeResult;

    /** Whether this method is enabled for the device (config/feature flag). */
    public function isEnabled(Device $device): bool;

    /** Whether this method has sufficient configuration to attempt polling. */
    public function isConfigured(Device $device): bool;

    /** null = unknown, true/false = last known result. */
    public function lastCheckSuccessful(Device $device): ?bool;

    /** UI/form schema for device-specific settings. */
    public function getDeviceSettings(): array;

    /** Resolved credentials for this device, or null if not applicable. */
    public function getSecret(Device $device): ?SecretData;
}

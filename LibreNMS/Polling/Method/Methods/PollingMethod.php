<?php

namespace LibreNMS\Polling\Method\Methods;

use App\Models\Device;
use App\Models\DevicePollingMethod;
use LibreNMS\Enum\ProcessType;
use LibreNMS\Enum\SecretType;
use LibreNMS\Exceptions\MissingSecretException;
use LibreNMS\Polling\Method\Config\PollingMethodConfig;
use LibreNMS\Polling\Method\Definitions\PollingMethodDefinition;
use LibreNMS\Polling\Method\ProbeResult;

/**
 * @template-covariant TConfig of PollingMethodConfig
 */
abstract class PollingMethod
{
    public function secretType(): ?SecretType
    {
        return null;
    }

    /**
     * Check that the method works with this config.
     * Each method chooses how: a quick check, or fetching the data its modules use, which they then read from the result instead of sending the request again.
     * Must avoid side effects on the target where possible.
     * Returns a failure rather than throwing for an unreachable target.
     * Called at most once per method per poll or discovery, see PerDeviceMethodResults.
     */
    abstract public function probe(Device $device, PollingMethodConfig $config): ProbeResult;

    /**
     * Discover or validate a candidate polling method for a device.
     */
    public function discover(Device $device, DevicePollingMethod $deviceMethod): ProbeResult
    {
        return $this->probe($device, $this->config($device, $deviceMethod));
    }

    /**
     * Identify a newly added device (sysName, os, etc.) after this method checked successfully.
     * Only methods that can query the device for its identity implement this.
     */
    public function enrichDeviceMetadata(Device $device): void
    {
    }

    /**
     * Modules this method enables or disables by default, like the os modules settings. The device settings override them.
     *
     * @return array<string, bool>
     */
    public function modules(ProcessType $type): array
    {
        return [];
    }

    /**
     * The source of module data this method provides, it implements module interfaces such as VminfoDiscovery.
     * Modules get it through OS::dataSources() when the method is enabled and its check succeeds.
     * It is created before the method is checked, so it must not query the device when constructed.
     */
    public function dataSource(Device $device, PollingMethodConfig $config): ?object
    {
        return null;
    }

    /**
     * Attach a secret to a method that has none and will be saved without a check.
     *
     * @throws MissingSecretException
     */
    public function assignDefaultSecret(DevicePollingMethod $deviceMethod): void
    {
    }

    /**
     * The settings of this method: form fields, validation rules and casts.
     */
    abstract public function definition(): PollingMethodDefinition;

    /**
     * Whether a newly added method of this type affects device availability.
     */
    abstract public function defaultAffectsAvailability(): bool;

    /**
     * The value of each setting when it is not set, keyed like the definition fields.
     *
     * @return array<string, mixed>
     */
    abstract public function defaults(?Device $device = null): array;

    /**
     * The config for the device from its stored method, or the defaults if the device does not have this method.
     *
     * @return TConfig
     */
    abstract public function config(Device $device, ?DevicePollingMethod $deviceMethod = null): PollingMethodConfig;
}

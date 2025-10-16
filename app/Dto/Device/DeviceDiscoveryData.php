<?php

namespace App\Dto\Device;

readonly class DeviceDiscoveryData
{
    public function __construct(
        public bool $force = false,
        public bool $pingFallback = false,
    ) {
    }
}

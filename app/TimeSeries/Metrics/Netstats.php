<?php

namespace App\TimeSeries\Metrics;

use App\TimeSeries\Metric;
use InvalidArgumentException;

/**
 * Written by LibreNMS\Modules\Netstats::poll()
 */
final readonly class Netstats implements Metric
{
    public const TYPES = ['icmp', 'ip', 'ip_forward', 'snmp', 'tcp', 'udp'];

    public function __construct(
        private int $deviceId,
        private string $type,
    ) {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown netstats type: $type");
        }
    }

    public function deviceId(): int
    {
        return $this->deviceId;
    }

    public function name(): string
    {
        return "netstats-$this->type";
    }

    public function labels(): array
    {
        return [];
    }
}

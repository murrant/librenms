<?php

namespace LibreNMS\Data\Definitions;

enum StorageType
{
    case COUNTER;
    case GAUGE;
    case DERIVE;
    case ABSOLUTE;
    case DCOUNTER;
    case DDERIVE;
    case HISTOGRAM;
    case SUMMARY;

    public function toRrdType(): string
    {
        return match ($this) {
            self::HISTOGRAM => 'COUNTER',
            self::SUMMARY => 'GAUGE',
            default => $this->name,
        };
    }

    public function toPrometheusType(): string
    {
        return match ($this) {
            self::COUNTER, self::DERIVE, self::ABSOLUTE, self::DCOUNTER, self::DDERIVE => 'Counter',
            self::HISTOGRAM => 'Histogram',
            self::SUMMARY => 'Summary',
            self::GAUGE => 'Gauge',
        };
    }
}

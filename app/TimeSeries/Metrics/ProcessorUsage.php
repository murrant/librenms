<?php

namespace App\TimeSeries\Metrics;

use App\Models\Processor;
use App\TimeSeries\Metric;

/**
 * Written by LibreNMS\Device\Processor::poll()
 */
final readonly class ProcessorUsage implements Metric
{
    public function __construct(
        private int $deviceId,
        private string $type,
        private int|string $index,
    ) {
    }

    public static function for(Processor $processor): self
    {
        return new self($processor->device_id, $processor->processor_type, $processor->processor_index);
    }

    public function deviceId(): int
    {
        return $this->deviceId;
    }

    public function name(): string
    {
        return 'processor';
    }

    public function labels(): array
    {
        return ['processor_type' => $this->type, 'processor_index' => $this->index];
    }
}

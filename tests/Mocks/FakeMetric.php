<?php

namespace LibreNMS\Tests\Mocks;

use App\TimeSeries\Metric;

/**
 * A metric for tests that resolve paths with a fake resolver
 */
final readonly class FakeMetric implements Metric
{
    /**
     * @param  array<string, string|int>  $labels
     */
    public function __construct(
        private string $name,
        private array $labels = [],
        private int $deviceId = 1,
    ) {
    }

    public function deviceId(): int
    {
        return $this->deviceId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function labels(): array
    {
        return $this->labels;
    }
}

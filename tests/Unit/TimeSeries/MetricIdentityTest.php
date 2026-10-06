<?php

namespace LibreNMS\Tests\Unit\TimeSeries;

use App\TimeSeries\MetricIdentity;
use InvalidArgumentException;
use LibreNMS\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MetricIdentityTest extends TestCase
{
    public function test_it_can_be_instantiated_with_name_and_labels(): void
    {
        $identity = new MetricIdentity('mempool', ['device_id' => 1, 'mempool_index' => 10]);

        $this->assertEquals('mempool', $identity->name);
        $this->assertEquals(['device_id' => 1, 'mempool_index' => 10], $identity->labels);
    }

    public function test_it_normalizes_labels_by_sorting_keys(): void
    {
        $identity = new MetricIdentity('mempool', [
            'mempool_index' => 10,
            'device_id' => 1,
        ]);

        $expected = [
            'device_id' => 1,
            'mempool_index' => 10,
        ];

        $this->assertEquals($expected, $identity->normalizedLabels());
    }

    public function test_it_throws_exception_for_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Metric name cannot be empty.');

        new MetricIdentity('', []);
    }

    public function test_it_throws_exception_for_invalid_label_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Metric label names must be non-empty strings.');

        new MetricIdentity('mempool', ['' => 'value']);
    }

    /**
     * @return array<string, array{0: array<mixed>}>
     */
    public static function nonScalarLabels(): array
    {
        return ['array value' => [['key' => []]]];
    }

    /**
     * @param  array<mixed>  $labels  untyped input is validated at runtime
     */
    #[DataProvider('nonScalarLabels')]
    public function test_it_throws_exception_for_non_scalar_label_value(array $labels): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Metric label [key] must be scalar or null.');

        new MetricIdentity('mempool', $labels);
    }
}

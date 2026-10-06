<?php

namespace LibreNMS\Tests\Unit\TimeSeries;

use App\TimeSeries\Exceptions\InvalidMetric;
use App\TimeSeries\MetricIdentity;
use App\TimeSeries\MetricSchema;
use LibreNMS\Tests\TestCase;

class MetricSchemaTest extends TestCase
{
    public function testOrdersValuesBySchemaNotInput(): void
    {
        $identity = new MetricIdentity('processor', ['processor_index' => 3, 'device_id' => 1, 'processor_type' => 'hr']);

        $this->assertSame(['hr', 3], (new MetricSchema)->orderedValues($identity));
    }

    public function testDeviceOnlyMetric(): void
    {
        $this->assertSame([], (new MetricSchema)->orderedValues(new MetricIdentity('netstats-ip', ['device_id' => 1])));
    }

    public function testUnknownMetric(): void
    {
        $this->expectException(InvalidMetric::class);
        $this->expectExceptionMessage('Unknown metric nope');

        (new MetricSchema)->orderedValues(new MetricIdentity('nope', ['device_id' => 1]));
    }

    public function testMissingLabel(): void
    {
        $this->expectException(InvalidMetric::class);
        $this->expectExceptionMessage('missing: [processor_index]');

        (new MetricSchema)->orderedValues(new MetricIdentity('processor', ['device_id' => 1, 'processor_type' => 'hr']));
    }

    public function testUnexpectedLabel(): void
    {
        $this->expectException(InvalidMetric::class);
        $this->expectExceptionMessage('unexpected: [extra]');

        (new MetricSchema)->orderedValues(new MetricIdentity('netstats-ip', ['device_id' => 1, 'extra' => 'x']));
    }
}

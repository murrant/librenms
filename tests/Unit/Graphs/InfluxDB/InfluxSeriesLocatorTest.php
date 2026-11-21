<?php

namespace LibreNMS\Tests\Unit\Graphs\InfluxDB;

use App\Data\TimeSeries\MetricIdentity;
use App\Graphs\InfluxDB\InfluxSeriesLocator;
use App\Models\Device;
use App\Models\Port;
use LibreNMS\Tests\InMemoryDbTestCase;

class InfluxSeriesLocatorTest extends InMemoryDbTestCase
{
    public function test_a_metric_is_found_as_identified_and_under_its_legacy_names(): void
    {
        $metric = new MetricIdentity('processor', ['hostname' => 'router', 'type' => 'hr', 'index' => '196608']);

        $this->assertEquals([
            $metric,
            new MetricIdentity('processors', ['hostname' => 'router', 'processor_type' => 'hr', 'processor_index' => '196608']),
        ], app(InfluxSeriesLocator::class)->locate($metric));
    }

    public function test_a_metric_without_legacy_names_is_found_as_identified(): void
    {
        $metric = new MetricIdentity('uptime', ['hostname' => 'router']);

        $this->assertEquals([$metric], app(InfluxSeriesLocator::class)->locate($metric));
    }

    public function test_ports_are_found_by_their_if_index(): void
    {
        $device = Device::factory()->create(['hostname' => 'router']);
        $port = Port::factory()->for($device)->create(['ifIndex' => 12]);
        $metric = $port->metricIdentity('port');

        $this->assertEquals([
            new MetricIdentity('port', ['hostname' => 'router', 'id' => (string) $port->port_id]),
            new MetricIdentity('ports', ['hostname' => 'router', 'ifIndex' => '12']),
        ], app(InfluxSeriesLocator::class)->locate($metric));
    }

    public function test_a_deleted_port_is_only_found_as_identified(): void
    {
        $metric = new MetricIdentity('port', ['hostname' => 'router', 'id' => 999999]);

        $this->assertEquals([new MetricIdentity('port', ['hostname' => 'router', 'id' => '999999'])], app(InfluxSeriesLocator::class)->locate($metric));
    }

    public function test_empty_tags_are_stored_as_blank(): void
    {
        $metric = new MetricIdentity('processor', ['hostname' => 'router', 'type' => '', 'index' => 0]);

        $this->assertEquals([
            new MetricIdentity('processor', ['hostname' => 'router', 'type' => '_blank_', 'index' => '_blank_']),
            new MetricIdentity('processors', ['hostname' => 'router', 'processor_type' => '_blank_', 'processor_index' => '_blank_']),
        ], app(InfluxSeriesLocator::class)->locate($metric));
    }
}

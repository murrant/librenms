<?php

namespace LibreNMS\Tests\Feature\Graphs;

use App\Facades\LibrenmsConfig;
use App\Graphing\Definition\Layout;
use App\Graphing\GraphQuery;
use App\Graphs\Device\NetstatIpGraph;
use App\Graphs\Device\ProcessorGraph;
use App\Models\Device;
use App\Models\Processor;
use App\TimeSeries\Metrics\ProcessorUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LibreNMS\Tests\TestCase;

class GraphDefinitionsTest extends TestCase
{
    use RefreshDatabase;

    public function testProcessorLines(): void
    {
        $device = Device::factory()->create(['os' => 'linux']);
        Processor::factory()->for($device)->count(2)->create();
        LibrenmsConfig::set('os.linux.processor_stacked', false);

        $graph = new ProcessorGraph;
        $query = GraphQuery::fromVars(['type' => 'device_processor', 'device' => $device->device_id]);
        $definition = $graph->define($graph->subject($query), $query);

        $this->assertSame(Layout::Lines, $definition->layout);
        $this->assertCount(2, $definition->series);
        $this->assertTrue($definition->series[0]->optional);
        $this->assertTrue($definition->series[0]->area);
        $this->assertSame(1.0, $definition->series[0]->multiplier);
        $this->assertInstanceOf(ProcessorUsage::class, $definition->series[0]->metric);
        $this->assertSame($device->device_id, $definition->series[0]->metric->deviceId());
        $this->assertSame(100.0, $definition->axis->max);
    }

    public function testProcessorStacked(): void
    {
        $device = Device::factory()->create(['os' => 'linux']);
        Processor::factory()->for($device)->count(4)->create();
        LibrenmsConfig::set('os.linux.processor_stacked', true);

        $graph = new ProcessorGraph;
        $query = GraphQuery::fromVars(['type' => 'device_processor', 'device' => $device->device_id]);
        $definition = $graph->define($graph->subject($query), $query);

        $this->assertSame(Layout::StackedArea, $definition->layout);
        $this->assertSame('oranges', $definition->palette);
        $this->assertSame(0.25, $definition->series[0]->multiplier);
        $this->assertTrue($definition->legendRawValues);
    }

    public function testNetstatIp(): void
    {
        $device = Device::factory()->create();

        $graph = new NetstatIpGraph;
        $query = GraphQuery::fromVars(['type' => 'device_netstat_ip', 'id' => $device->device_id]);
        $subject = $graph->subject($query);
        $definition = $graph->define($subject, $query);

        $this->assertCount(7, $definition->series);
        $this->assertSame(['netstats-ip'], array_values(array_unique(array_map(fn ($s) => $s->metric->name(), $definition->series))));
        $inverted = array_values(array_map(fn ($s) => $s->field, array_filter($definition->series, fn ($s) => $s->invert)));
        $this->assertSame(['ipOutRequests', 'ipOutDiscards', 'ipOutNoRoutes'], $inverted);
        $this->assertFalse($definition->series[0]->optional);
        $this->assertStringEndsWith(':: IP NetStats', $graph->title($subject));
    }
}

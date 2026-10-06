<?php

namespace LibreNMS\Tests\Unit\Graphing;

use App\Facades\Rrd;
use App\Graphing\Exceptions\GraphNoData;
use App\Graphing\Exceptions\GraphRenderFailed;
use App\Graphing\GraphQuery;
use App\Graphing\Plans\RrdCommand;
use App\Graphing\Rrd\RrdtoolRenderer;
use LibreNMS\Enum\ImageFormat;
use LibreNMS\Exceptions\RrdGraphException;
use LibreNMS\Exceptions\RrdNotFoundException;
use LibreNMS\Tests\TestCase;

class RrdtoolRendererTest extends TestCase
{
    public function testRendersImage(): void
    {
        Rrd::shouldReceive('graph')->with(['--start', 1])->once()->andReturn('image-data');

        $image = (new RrdtoolRenderer)->render(new RrdCommand(['--start', 1], 'My Graph'), $this->query());

        $this->assertSame('image-data', $image->data);
        $this->assertSame('My Graph', $image->title);
        $this->assertSame(ImageFormat::Svg, $image->format);
    }

    public function testMissingLocalRrdIsNoData(): void
    {
        Rrd::shouldReceive('graph')->andThrow(new RrdNotFoundException("opening '/opt/librenms/rrd/host/poller-perf.rrd': No such file or directory"));

        try {
            (new RrdtoolRenderer)->render(new RrdCommand([], ''), $this->query());
            $this->fail('Expected GraphNoData');
        } catch (GraphNoData $e) {
            $this->assertSame(['poller-perf.rrd'], $e->missing);
            $this->assertSame('No Data', $e->shortText());
        }
    }

    public function testMissingRrdcachedRrdIsNoData(): void
    {
        Rrd::shouldReceive('graph')->andThrow(new RrdNotFoundException("rrdcached@unix:/run/rrdcached.sock: rrd_fetch_r failed: opening '/var/lib/rrdcached/db/host/poller-perf.rrd': No such file or directory"));

        $this->expectException(GraphNoData::class);
        $this->expectExceptionMessage('No Data file poller-perf.rrd');

        (new RrdtoolRenderer)->render(new RrdCommand([], ''), $this->query());
    }

    public function testRrdtoolErrorIsRenderFailure(): void
    {
        Rrd::shouldReceive('graph')->andThrow(new RrdGraphException('invalid DEF', 'Error'));

        $this->expectException(GraphRenderFailed::class);
        $this->expectExceptionMessage('Error: invalid DEF');

        (new RrdtoolRenderer)->render(new RrdCommand([], ''), $this->query());
    }

    private function query(): GraphQuery
    {
        return GraphQuery::fromVars(['type' => 'device_poller_perf', 'graph_type' => 'svg']);
    }
}

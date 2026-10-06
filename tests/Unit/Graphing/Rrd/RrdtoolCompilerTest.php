<?php

namespace LibreNMS\Tests\Unit\Graphing\Rrd;

use App\Facades\LibrenmsConfig;
use App\Graphing\Definition\Axis;
use App\Graphing\Definition\GraphDefinition;
use App\Graphing\Definition\Layout;
use App\Graphing\Definition\Series;
use App\Graphing\GraphQuery;
use App\Graphing\Rrd\RrdtoolCompiler;
use App\TimeSeries\Contracts\RrdPathResolver;
use App\TimeSeries\MetricIdentity;
use LibreNMS\Data\Store\Rrd;
use LibreNMS\RRD\RrdPath;
use LibreNMS\Tests\TestCase;

class RrdtoolCompilerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        LibrenmsConfig::set('rrd_dir', '/rrd');
        LibrenmsConfig::set('rrdcached', false);
        LibrenmsConfig::set('graph_colours.mixed', ['AA0000', 'BB0000', 'CC0000']);
        LibrenmsConfig::set('webui.graph_stacked', false);
    }

    public function testCompilesLines(): void
    {
        $compiled = $this->compiler()->compile($this->definition(), $this->query());

        $this->assertContains('DEF:s0=/rrd/host/a.rrd:usage:AVERAGE', $compiled->options);
        $this->assertContains('DEF:s0min=/rrd/host/a.rrd:usage:MIN', $compiled->options);
        $this->assertContains('DEF:s1=/rrd/host/b.rrd:usage:AVERAGE', $compiled->options);
        $this->assertContains('CDEF:s1i=s1,-1,*', $compiled->options);
        $this->assertContains('LINE1.25:s0#AA0000:' . $this->descr('First'), $compiled->options);
        $this->assertContains('LINE1.25:s1i#BB0000:' . $this->descr('Second'), $compiled->options);
        $this->assertContains('AREA:s0#AA000020', $compiled->options);
        $this->assertContains('GPRINT:s0:AVERAGE:%5.2lf%s%\n', $compiled->options);
        $this->assertContains('HRULE:0#555555', $compiled->options);
        $this->assertSame(['host/a.rrd', 'host/b.rrd'], array_keys($compiled->files));
        $this->assertSame('My Graph', $compiled->title);

        // axis scale is applied to the base options
        $this->assertSame('0', (string) $compiled->options[array_search('-l', $compiled->options) + 1]);
        $this->assertSame('100', (string) $compiled->options[array_search('-u', $compiled->options) + 1]);
    }

    public function testSkippedSeriesKeepTheirColors(): void
    {
        $compiled = $this->compiler()->compile($this->definition(), $this->query(), ['a']);

        $this->assertNotContains('DEF:s0=/rrd/host/a.rrd:usage:AVERAGE', $compiled->options);
        $this->assertContains('DEF:s0=/rrd/host/b.rrd:usage:AVERAGE', $compiled->options);
        $this->assertContains('LINE1.25:s0i#BB0000:' . $this->descr('Second'), $compiled->options);
        $this->assertContains('COMMENT:1 series without data not shown\l', $compiled->options);
        $this->assertSame(['host/b.rrd'], array_keys($compiled->files));
    }

    public function testSkippingEverythingIsEmpty(): void
    {
        $compiled = $this->compiler()->compile($this->definition(), $this->query(), ['a', 'b']);

        $this->assertTrue($compiled->isEmpty());
        $this->assertSame([], $compiled->options);
    }

    public function testCompilesStackedAreaWithMultiplier(): void
    {
        $definition = new GraphDefinition([
            new Series('a', new MetricIdentity('a'), 'usage', 'First', multiplier: 0.5),
            new Series('b', new MetricIdentity('b'), 'usage', 'Second', multiplier: 0.5),
        ], Layout::StackedArea, new Axis('Load %'), legendRawValues: true);

        $options = $this->compiler()->compile($definition, $this->query(['previous' => 'yes']))->options;

        $this->assertContains('CDEF:s0m=s0,0.5,*', $options);
        $this->assertContains('AREA:s0m#AA0000:' . $this->descr('First', 16), $options);
        $this->assertContains('AREA:s1m#BB0000:' . $this->descr('Second', 16) . ':STACK', $options);
        $this->assertContains('GPRINT:s0:LAST:%5.2lf%s', $options); // raw values in the legend
        $this->assertContains('CDEF:previous=s0pz,s1pz,+', $options);
    }

    public function testFilesFeedingManySeries(): void
    {
        $metric = new MetricIdentity('shared');
        $definition = new GraphDefinition([
            new Series('in', $metric, 'in', 'In'),
            new Series('out', $metric, 'out', 'Out'),
        ]);

        $compiled = $this->compiler()->compile($definition, $this->query());

        $this->assertSame(['host/shared.rrd' => ['in', 'out']], array_map(fn ($file) => $file['keys'], $compiled->files));
        $this->assertSame(['in', 'out'], $compiled->keysFor([RrdPath::make('host', 'shared.rrd')]));
    }

    public function testFindsFileInLocalAndRrdcachedErrors(): void
    {
        $compiled = $this->compiler()->compile($this->definition(), $this->query());

        $this->assertSame('host/b.rrd', $compiled->fileInMessage("opening '/rrd/host/b.rrd': No such file or directory")?->relativePath());
        $this->assertSame('host/a.rrd', $compiled->fileInMessage("rrdcached@unix:/run/sock: rrd_fetch_r failed: opening '/var/lib/rrdcached/db/host/a.rrd': No such file or directory")?->relativePath());
        $this->assertNull($compiled->fileInMessage("opening '/rrd/host/other.rrd': No such file or directory"));
    }

    private function descr(string $label, int $length = 14): string
    {
        return Rrd::fixedSafeDescr($label, $length);
    }

    private function definition(): GraphDefinition
    {
        return new GraphDefinition([
            new Series('a', new MetricIdentity('a'), 'usage', 'First', area: true),
            new Series('b', new MetricIdentity('b'), 'usage', 'Second', invert: true),
        ], axis: new Axis('Load', '%', 0, 100), title: 'My Graph');
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    private function query(array $vars = []): GraphQuery
    {
        return GraphQuery::fromVars(['type' => 'device_test', 'from' => 1700000000, 'to' => 1700086400] + $vars);
    }

    private function compiler(): RrdtoolCompiler
    {
        return new RrdtoolCompiler(new class implements RrdPathResolver
        {
            public function resolve(MetricIdentity $identity): RrdPath
            {
                return RrdPath::make('host', $identity->name . '.rrd');
            }
        });
    }
}

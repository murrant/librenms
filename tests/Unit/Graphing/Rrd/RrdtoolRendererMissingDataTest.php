<?php

namespace LibreNMS\Tests\Unit\Graphing\Rrd;

use App\Facades\LibrenmsConfig;
use App\Facades\Rrd;
use App\Graphing\Definition\GraphDefinition;
use App\Graphing\Definition\Series;
use App\Graphing\Exceptions\GraphNoData;
use App\Graphing\Exceptions\GraphRenderFailed;
use App\Graphing\GraphQuery;
use App\Graphing\Rrd\RrdtoolRenderer;
use App\TimeSeries\Contracts\RrdPathResolver;
use App\TimeSeries\Metric;
use LibreNMS\Exceptions\RrdNotFoundException;
use LibreNMS\RRD\RrdPath;
use LibreNMS\Tests\Mocks\FakeMetric;
use LibreNMS\Tests\TestCase;
use Mockery;

class RrdtoolRendererMissingDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        LibrenmsConfig::set('rrd_dir', '/rrd');
        LibrenmsConfig::set('rrdcached', false);

        $this->app->instance(RrdPathResolver::class, new class implements RrdPathResolver
        {
            public function resolve(Metric $metric): RrdPath
            {
                return RrdPath::make('host', $metric->name() . '.rrd');
            }
        });
    }

    public function testDrawsWithoutCheckingFiles(): void
    {
        Rrd::shouldReceive('graph')->once()->andReturn('image');
        Rrd::shouldReceive('missingFiles')->never();

        $image = $this->renderer()->renderDefinition($this->definition(optional: true), $this->query());

        $this->assertSame('image', $image->data);
        $this->assertSame([], $image->missing);
    }

    public function testMissingOptionalSeriesAreDroppedAfterOneCheck(): void
    {
        Rrd::shouldReceive('graph')->once()->ordered()->andThrow($this->notFound('/rrd/host/a.rrd'));
        Rrd::shouldReceive('missingFiles')->once()
            ->with(Mockery::on(fn ($paths) => array_map(fn ($p) => $p->relativePath(), $paths) == ['host/a.rrd', 'host/b.rrd', 'host/c.rrd']))
            ->andReturn([RrdPath::make('host', 'a.rrd'), RrdPath::make('host', 'c.rrd')]);
        Rrd::shouldReceive('graph')->once()->ordered()
            ->with(Mockery::on(fn ($options) => in_array('DEF:s0=/rrd/host/b.rrd:value:AVERAGE', $options)
                && ! str_contains(implode(' ', $options), 'a.rrd')
                && ! str_contains(implode(' ', $options), 'c.rrd')))
            ->andReturn('partial');

        $image = $this->renderer()->renderDefinition($this->definition(optional: true), $this->query());

        $this->assertSame('partial', $image->data);
        $this->assertSame(['a.rrd', 'c.rrd'], $image->missing);
    }

    public function testReportedFileIsTrustedOverTheListing(): void
    {
        Rrd::shouldReceive('graph')->once()->ordered()->andThrow($this->notFound('/rrd/host/b.rrd'));
        Rrd::shouldReceive('missingFiles')->once()->andReturn([]);
        Rrd::shouldReceive('graph')->once()->ordered()->andReturn('partial');

        $image = $this->renderer()->renderDefinition($this->definition(optional: true), $this->query());

        $this->assertSame(['b.rrd'], $image->missing);
    }

    public function testMissingRequiredSeriesIsNoData(): void
    {
        Rrd::shouldReceive('graph')->once()->andThrow($this->notFound('/rrd/host/a.rrd'));
        Rrd::shouldReceive('missingFiles')->once()->andReturn([RrdPath::make('host', 'a.rrd')]);

        $this->expectException(GraphNoData::class);
        $this->expectExceptionMessage('No Data file a.rrd');

        $this->renderer()->renderDefinition($this->definition(optional: false), $this->query());
    }

    public function testAllSeriesMissingIsNoData(): void
    {
        Rrd::shouldReceive('graph')->once()->andThrow($this->notFound('/rrd/host/a.rrd'));
        Rrd::shouldReceive('missingFiles')->once()->andReturn([RrdPath::make('host', 'a.rrd'), RrdPath::make('host', 'b.rrd'), RrdPath::make('host', 'c.rrd')]);

        try {
            $this->renderer()->renderDefinition($this->definition(optional: true), $this->query());
            $this->fail('Expected GraphNoData');
        } catch (GraphNoData $e) {
            $this->assertSame(['a.rrd', 'b.rrd', 'c.rrd'], $e->missing);
        }
    }

    public function testRetryThatStillFailsIsNoData(): void
    {
        Rrd::shouldReceive('graph')->twice()->andThrow($this->notFound('/rrd/host/a.rrd'), $this->notFound('/rrd/host/b.rrd'));
        Rrd::shouldReceive('missingFiles')->once()->andReturn([RrdPath::make('host', 'a.rrd')]);

        $this->expectException(GraphNoData::class);

        $this->renderer()->renderDefinition($this->definition(optional: true), $this->query());
    }

    public function testMissingSecondFile(): void
    {
        Rrd::shouldReceive('graph')->once()->ordered()->andThrow($this->notFound('/rrd/host/b.rrd'));
        Rrd::shouldReceive('missingFiles')->once()->andReturn([RrdPath::make('host', 'b.rrd')]);
        Rrd::shouldReceive('graph')->once()->ordered()
            ->with(Mockery::on(fn ($options) => ! str_contains(implode(' ', $options), 'b.rrd')))
            ->andReturn('partial');

        $image = $this->renderer()->renderDefinition($this->definition(optional: true), $this->query());

        $this->assertSame(['b.rrd'], $image->missing);
    }

    public function testRequiredAndOptionalMissingIsNoData(): void
    {
        $definition = new GraphDefinition([
            new Series('a', new FakeMetric('a'), 'value', 'A', optional: true),
            new Series('b', new FakeMetric('b'), 'value', 'B'),
            new Series('c', new FakeMetric('c'), 'value', 'C', optional: true),
        ]);
        Rrd::shouldReceive('graph')->once()->andThrow($this->notFound('/rrd/host/a.rrd'));
        Rrd::shouldReceive('missingFiles')->once()->andReturn([RrdPath::make('host', 'a.rrd'), RrdPath::make('host', 'b.rrd')]);

        try {
            $this->renderer()->renderDefinition($definition, $this->query());
            $this->fail('Expected GraphNoData');
        } catch (GraphNoData $e) {
            $this->assertSame(['a.rrd', 'b.rrd'], $e->missing);
        }
    }

    public function testOnlyOptionalMissingWhileRequiredPresentRedraws(): void
    {
        $definition = new GraphDefinition([
            new Series('a', new FakeMetric('a'), 'value', 'A', optional: true),
            new Series('b', new FakeMetric('b'), 'value', 'B'),
        ]);
        Rrd::shouldReceive('graph')->once()->ordered()->andThrow($this->notFound('/rrd/host/a.rrd'));
        Rrd::shouldReceive('missingFiles')->once()->andReturn([RrdPath::make('host', 'a.rrd')]);
        Rrd::shouldReceive('graph')->once()->ordered()->andReturn('partial');

        $image = $this->renderer()->renderDefinition($definition, $this->query());

        $this->assertSame(['a.rrd'], $image->missing);
    }

    public function testAllRequiredMissingIsNoData(): void
    {
        Rrd::shouldReceive('graph')->once()->andThrow($this->notFound('/rrd/host/a.rrd'));
        Rrd::shouldReceive('missingFiles')->once()->andReturn([RrdPath::make('host', 'a.rrd'), RrdPath::make('host', 'b.rrd'), RrdPath::make('host', 'c.rrd')]);
        Rrd::shouldReceive('graph')->never()->with(Mockery::any());

        $this->expectException(GraphNoData::class);

        $this->renderer()->renderDefinition($this->definition(optional: false), $this->query());
    }

    public function testRrdcachedPrefixedPath(): void
    {
        Rrd::shouldReceive('graph')->once()->ordered()->andThrow(new RrdNotFoundException(
            "rrdcached@unix:/run/rrdcached.sock: rrd_fetch_r failed: opening '/var/lib/rrdcached/db/host/c.rrd': No such file or directory"));
        Rrd::shouldReceive('missingFiles')->once()->andReturn([]);
        Rrd::shouldReceive('graph')->once()->ordered()->andReturn('partial');

        $image = $this->renderer()->renderDefinition($this->definition(optional: true), $this->query());

        $this->assertSame(['c.rrd'], $image->missing);
    }

    public function testNotFoundWithoutPathIsRenderFailure(): void
    {
        Rrd::shouldReceive('graph')->once()->andThrow(new RrdNotFoundException('No such file or directory'));
        Rrd::shouldReceive('missingFiles')->never();

        $this->expectException(GraphRenderFailed::class);

        $this->renderer()->renderDefinition($this->definition(optional: true), $this->query());
    }

    public function testUnknownMissingFileIsRenderFailure(): void
    {
        Rrd::shouldReceive('graph')->once()->andThrow($this->notFound('/rrd/host/unrelated.rrd'));
        Rrd::shouldReceive('missingFiles')->never();

        $this->expectException(GraphRenderFailed::class);

        $this->renderer()->renderDefinition($this->definition(optional: true), $this->query());
    }

    private function definition(bool $optional): GraphDefinition
    {
        return new GraphDefinition(array_map(
            fn (string $name) => new Series($name, new FakeMetric($name), 'value', strtoupper($name), optional: $optional),
            ['a', 'b', 'c'],
        ));
    }

    private function notFound(string $file): RrdNotFoundException
    {
        return new RrdNotFoundException("opening '$file': No such file or directory");
    }

    private function query(): GraphQuery
    {
        return GraphQuery::fromVars(['type' => 'device_test', 'from' => 1700000000, 'to' => 1700086400]);
    }

    private function renderer(): RrdtoolRenderer
    {
        return app(RrdtoolRenderer::class);
    }
}

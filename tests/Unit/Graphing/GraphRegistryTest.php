<?php

namespace LibreNMS\Tests\Unit\Graphing;

use App\Graphing\Contracts\GraphHandler;
use App\Graphing\Contracts\RenderPlan;
use App\Graphing\Exceptions\UnknownGraph;
use App\Graphing\GraphAccess;
use App\Graphing\GraphDescription;
use App\Graphing\GraphQuery;
use App\Graphing\GraphRegistry;
use App\Graphing\GraphSubject;
use App\Graphing\Legacy\LegacyGraphHandler;
use InvalidArgumentException;
use LibreNMS\Tests\TestCase;

class GraphRegistryTest extends TestCase
{
    public function testFallsBackToLegacyTemplates(): void
    {
        $this->assertInstanceOf(LegacyGraphHandler::class, (new GraphRegistry)->handler('device', 'poller_perf'));
    }

    public function testLegacyGenericTemplateIsUsed(): void
    {
        // sensor graphs have no per subtype template, only generic.inc.php
        $this->assertInstanceOf(LegacyGraphHandler::class, (new GraphRegistry)->handler('sensor', 'temperature'));
    }

    public function testUnknownGraphThrows(): void
    {
        $this->expectException(UnknownGraph::class);
        (new GraphRegistry)->handler('device', 'doesnotexist');
    }

    public function testUnknownTypeThrows(): void
    {
        $this->expectException(UnknownGraph::class);
        (new GraphRegistry)->handler('doesnotexist', 'bits');
    }

    public function testPathTraversalIsRejected(): void
    {
        $this->expectException(UnknownGraph::class);
        (new GraphRegistry)->handler('device', '../port/bits');
    }

    public function testRegisteredGraphIsPreferredAndListed(): void
    {
        $registry = new GraphRegistry(['device_poller_perf' => FakeGraphHandler::class, 'device_fake' => FakeGraphHandler::class]);

        $this->assertInstanceOf(FakeGraphHandler::class, $registry->handler('device', 'poller_perf'));
        $this->assertInstanceOf(FakeGraphHandler::class, $registry->handler('device', 'fake'));
        $this->assertContains('fake', $registry->subtypes('device'));
        $this->assertContains('poller_perf', $registry->subtypes('device'));
        $this->assertSame(['fake' => ['required']], $registry->rules('device', 'fake'));
    }

    public function testRulesAreEmptyForUnknownGraphs(): void
    {
        $this->assertSame([], (new GraphRegistry)->rules('device', 'doesnotexist'));
    }

    public function testRejectsClassesThatAreNotHandlers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GraphRegistry(['device_fake' => \stdClass::class]); // @phpstan-ignore argument.type
    }

    public function testRejectsInvalidNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GraphRegistry(['../device' => FakeGraphHandler::class]);
    }
}

class FakeGraphHandler implements GraphHandler
{
    public function subject(GraphQuery $query, GraphAccess $access): GraphSubject
    {
        return new GraphSubject;
    }

    public function authorize(GraphSubject $subject, GraphAccess $access): bool
    {
        return true;
    }

    public function describe(GraphSubject $subject): GraphDescription
    {
        return new GraphDescription('Fake');
    }

    public function plan(GraphSubject $subject, GraphQuery $query): RenderPlan
    {
        throw new \LogicException('not implemented');
    }

    public function rules(): array
    {
        return ['fake' => ['required']];
    }
}

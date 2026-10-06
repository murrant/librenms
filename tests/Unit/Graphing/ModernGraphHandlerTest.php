<?php

namespace LibreNMS\Tests\Unit\Graphing;

use App\Graphing\BaseGraph;
use App\Graphing\Definition\GraphDefinition;
use App\Graphing\Definition\Series;
use App\Graphing\GraphAccess;
use App\Graphing\GraphTrust;
use App\Graphing\GraphQuery;
use App\Graphing\GraphSubject;
use App\Graphing\Modern\ModernGraphHandler;
use App\Models\Device;
use App\Models\User;
use Illuminate\Auth\Access\Gate as AccessGate;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use LibreNMS\Tests\Mocks\FakeMetric;
use LibreNMS\Tests\TestCase;
use Mockery;

class ModernGraphHandlerTest extends TestCase
{
    public function testTrustedAccessSkipsPolicies(): void
    {
        Gate::shouldReceive('forUser')->never();

        $this->assertTrue($this->handler()->authorize(new GraphSubject(new Device), GraphAccess::trusted(GraphTrust::SignedUrl)));
    }

    public function testUserAccessChecksTheGraphAbility(): void
    {
        $user = new User;
        $device = new Device;
        $gate = Mockery::mock(AccessGate::class);
        $gate->shouldReceive('allows')->with('view', $device)->once()->andReturn(false);
        Gate::shouldReceive('forUser')->with($user)->once()->andReturn($gate);

        $this->assertFalse($this->handler()->authorize(new GraphSubject($device), GraphAccess::user($user)));
    }

    public function testSubjectWithoutAuthorizableIsDenied(): void
    {
        $this->assertFalse($this->handler()->authorize(new GraphSubject, GraphAccess::user(new User)));
    }

    public function testPlanDefaultsTitle(): void
    {
        $plan = $this->handler()->plan(new GraphSubject, GraphQuery::fromVars(['type' => 'device_fake']));

        $this->assertSame('Fake Title', $plan->title);
    }

    public function testDefinitionRejectsDuplicateKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $metric = new FakeMetric('a');
        new GraphDefinition([new Series('a', $metric, 'x', 'X'), new Series('a', $metric, 'y', 'Y')]);
    }

    public function testDefinitionOptionalSeries(): void
    {
        $metric = new FakeMetric('a');
        $definition = new GraphDefinition([new Series('a', $metric, 'x', 'X', optional: true), new Series('b', $metric, 'y', 'Y')]);

        $this->assertTrue($definition->allOptional(['a']));
        $this->assertFalse($definition->allOptional(['a', 'b']));
    }

    private function handler(): ModernGraphHandler
    {
        return new ModernGraphHandler(new class extends BaseGraph
        {
            public function title(GraphSubject $subject): string
            {
                return 'Fake Title';
            }

            public function define(GraphSubject $subject, GraphQuery $query): GraphDefinition
            {
                return new GraphDefinition([]);
            }
        });
    }
}

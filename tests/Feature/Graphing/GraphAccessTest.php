<?php

namespace LibreNMS\Tests\Feature\Graphing;

use App\Facades\LibrenmsConfig;
use App\Graphing\Exceptions\GraphUnauthorized;
use App\Graphing\GraphAccess;
use App\Graphing\GraphTrust;
use App\Graphing\GraphQuery;
use App\Graphing\GraphService;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use LibreNMS\Tests\TestCase;
use LibreNMS\Util\Graph;
use LogicException;
use Spatie\Permission\Models\Role;

class GraphAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $rrdDir;

    protected function setUp(): void
    {
        parent::setUp();
        LibrenmsConfig::set('webui.graph_type', 'svg');
        LibrenmsConfig::set('rrd_dir', $this->rrdDir = sys_get_temp_dir() . '/librenms-graph-access-test-' . uniqid());
        LibrenmsConfig::set('rrdcached', false);
        mkdir($this->rrdDir);
        $this->app->forgetInstance(\LibreNMS\Data\Store\Rrd::class);
    }

    protected function tearDown(): void
    {
        @rmdir($this->rrdDir);

        parent::tearDown();
    }

    public function testRequestUser(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/graph');
        $request->setUserResolver(fn () => $user);

        $access = GraphAccess::fromRequest($request);

        $this->assertSame($user, $access->user);
        $this->assertFalse($access->isTrusted());
    }

    public function testRequestTrustedByMiddleware(): void
    {
        $request = Request::create('/graph');
        $request->attributes->set(GraphAccess::REQUEST_ATTRIBUTE, GraphTrust::SignedUrl);

        $access = GraphAccess::fromRequest($request);

        $this->assertNull($access->user);
        $this->assertTrue($access->isTrusted());
        $this->assertSame(GraphTrust::SignedUrl, $access->trust);
    }

    public function testTrustMustBeAGraphTrust(): void
    {
        $request = Request::create('/graph');
        $request->attributes->set(GraphAccess::REQUEST_ATTRIBUTE, 'signed-url');

        $this->expectException(GraphUnauthorized::class);
        GraphAccess::fromRequest($request);
    }

    public function testGuestRequestIsNotTrusted(): void
    {
        $this->expectException(GraphUnauthorized::class);
        GraphAccess::fromRequest(Request::create('/graph'));
    }

    public function testCurrentRequiresLogin(): void
    {
        $this->expectException(GraphUnauthorized::class);
        GraphAccess::current();
    }

    public function testAlertAccessRendersWithoutUser(): void
    {
        $device = Device::factory()->create();

        $image = Graph::getImage(['type' => 'device_poller_perf', 'device' => $device->device_id], GraphAccess::trusted(GraphTrust::Alert));

        $this->assertSame('Error', $image->title);
        $this->assertStringContainsString('No Data', $image->data);
    }

    public function testDefaultAccessRequiresUser(): void
    {
        $device = Device::factory()->create();

        $image = Graph::getImage(['type' => 'device_poller_perf', 'device' => $device->device_id]);

        $this->assertStringContainsString('Not logged in', $image->data);
    }

    public function testDefaultAccessUsesLoggedInUser(): void
    {
        Role::findOrCreate('admin');
        $device = Device::factory()->create();
        $this->actingAs(User::factory()->create()->assignRole('admin'));

        $image = Graph::getImage(['type' => 'device_poller_perf', 'device' => $device->device_id]);

        $this->assertStringContainsString('No Data', $image->data);
    }

    public function testLegacyGraphsRejectUserOtherThanLoggedIn(): void
    {
        $device = Device::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->expectException(LogicException::class);
        app(GraphService::class)->resolve(
            GraphQuery::fromVars(['type' => 'device_poller_perf', 'device' => $device->device_id]),
            GraphAccess::user(User::factory()->create()),
        );
    }
}

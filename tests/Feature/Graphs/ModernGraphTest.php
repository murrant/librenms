<?php

namespace LibreNMS\Tests\Feature\Graphs;

use App\Facades\LibrenmsConfig;
use App\Graphing\GraphAccess;
use App\Graphing\GraphQuery;
use App\Graphing\GraphRegistry;
use App\Graphing\GraphService;
use App\Graphing\Modern\ModernGraphHandler;
use App\Models\Device;
use App\Models\Processor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LibreNMS\Tests\TestCase;
use Spatie\Permission\Models\Role;

class ModernGraphTest extends TestCase
{
    use RefreshDatabase;

    private string $rrdDir;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        LibrenmsConfig::set('auth_mechanism', 'mysql');
        LibrenmsConfig::set('webui.graph_type', 'svg');
        LibrenmsConfig::set('webui.dynamic_graphs', false);
        LibrenmsConfig::set('allow_unauth_graphs', false);
        LibrenmsConfig::set('rrdcached', false);

        $this->rrdDir = sys_get_temp_dir() . '/librenms-modern-graph-test-' . uniqid();
        mkdir($this->rrdDir);
        LibrenmsConfig::set('rrd_dir', $this->rrdDir);
        $this->app->forgetInstance(\LibreNMS\Data\Store\Rrd::class);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->rrdDir));

        parent::tearDown();
    }

    public function testRegisteredGraphsAreModern(): void
    {
        $registry = app(GraphRegistry::class);

        $this->assertInstanceOf(ModernGraphHandler::class, $registry->handler('device', 'processor'));
        $this->assertInstanceOf(ModernGraphHandler::class, $registry->handler('device', 'netstat_ip'));
    }

    public function testProcessorGraphDrawsWithoutMissingProcessors(): void
    {
        $this->requireRrdtool();
        $device = Device::factory()->create();
        $present = Processor::factory()->for($device)->create(['processor_type' => 'hr', 'processor_index' => '1', 'processor_descr' => 'Present CPU']);
        Processor::factory()->for($device)->create(['processor_type' => 'hr', 'processor_index' => '2', 'processor_descr' => 'Absent CPU']);
        $this->createRrd($device->hostname, "processor-hr-$present->processor_index", 'usage');

        $response = $this->actingAs($this->adminUser())
            ->get("/graph?device=$device->device_id&type=device_processor");

        $response->assertOk();
        $response->assertHeader('Content-type', 'image/svg+xml');
        $this->assertStringStartsWith('<?xml', $response->getContent());

        // rrdtool draws svg text as paths, check what was drawn through the service
        $image = app(GraphService::class)->render(
            GraphQuery::fromVars(['type' => 'device_processor', 'device' => $device->device_id]),
            GraphAccess::trusted('test'),
        );
        $this->assertSame(['processor-hr-2.rrd'], $image->missing);
        $this->assertSame($device->displayName(), $image->title);
    }

    public function testProcessorGraphWithNoRrdsIsNoData(): void
    {
        $device = Device::factory()->create();
        Processor::factory()->for($device)->create();

        $response = $this->actingAs($this->adminUser())
            ->get("/graph?device=$device->device_id&type=device_processor");

        $response->assertStatus(500);
        $this->assertStringContainsString('No Data', $response->getContent());
    }

    public function testProcessorGraphWithoutProcessors(): void
    {
        $device = Device::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->get("/graph?device=$device->device_id&type=device_processor");

        $this->assertStringContainsString('No Processors', $response->getContent());
    }

    public function testUnknownDevice(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->get('/graph?device=999999&type=device_processor');

        $this->assertStringContainsString('Device not found', $response->getContent());
    }

    public function testUserWithoutPermissionIsDenied(): void
    {
        $device = Device::factory()->create();
        Processor::factory()->for($device)->create();

        $response = $this->actingAs(User::factory()->create(['enabled' => 1]))
            ->get("/graph?device=$device->device_id&type=device_processor");

        $this->assertStringContainsString('No Authorization', $response->getContent());
    }

    public function testTrustedGuestIsAllowed(): void
    {
        LibrenmsConfig::set('allow_unauth_graphs', true);
        $device = Device::factory()->create();

        $response = $this->get("/graph?device=$device->device_id&type=device_netstat_ip");

        $this->assertStringContainsString('No Data file netstats-ip.rrd', $response->getContent());
    }

    public function testGraphsPageAndCommand(): void
    {
        $device = Device::factory()->create(['hostname' => 'modern.example.com']);

        $response = $this->actingAs($this->adminUser())
            ->get("/graphs?device=$device->device_id&type=device_netstat_ip&showcommand=yes");

        $response->assertOk();
        $response->assertSee('RRDTool Command');
        $response->assertSee('modern.example.com/netstats-ip.rrd:ipInDelivers:AVERAGE', false);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['enabled' => 1])->assignRole('admin');
    }

    private function requireRrdtool(): void
    {
        exec('command -v rrdtool', $output, $code);
        if ($code !== 0) {
            $this->markTestSkipped('rrdtool is not installed');
        }
    }

    private function createRrd(string $hostname, string $name, string $ds): void
    {
        $dir = "$this->rrdDir/$hostname";
        @mkdir($dir);
        $file = escapeshellarg("$dir/$name.rrd");
        $start = time() - 3600;
        exec("rrdtool create $file --start $start --step 300 DS:$ds:GAUGE:600:U:U RRA:AVERAGE:0.5:1:600 RRA:MIN:0.5:1:600 RRA:MAX:0.5:1:600", result_code: $code);
        $this->assertSame(0, $code, 'Failed to create rrd');

        for ($time = $start + 300; $time < time(); $time += 300) {
            exec("rrdtool update $file $time:" . random_int(1, 90));
        }
    }
}

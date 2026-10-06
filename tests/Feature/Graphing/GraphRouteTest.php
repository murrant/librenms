<?php

namespace LibreNMS\Tests\Feature\Graphing;

use App\Facades\LibrenmsConfig;
use App\Models\Bill;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use LibreNMS\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Spatie\Permission\Models\Role;

class GraphRouteTest extends TestCase
{
    use RefreshDatabase;

    private string $rrdDir;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        LibrenmsConfig::set('auth_mechanism', 'mysql');
        LibrenmsConfig::set('webui.graph_type', 'svg'); // error images are svg text we can assert on
        LibrenmsConfig::set('allow_unauth_graphs', false);
        LibrenmsConfig::set('allow_unauth_graphs_cidr', []);
        LibrenmsConfig::set('rrdcached', false);

        $this->rrdDir = sys_get_temp_dir() . '/librenms-graph-test-' . uniqid();
        mkdir($this->rrdDir);
        LibrenmsConfig::set('rrd_dir', $this->rrdDir);
        $this->app->forgetInstance(\LibreNMS\Data\Store\Rrd::class);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->rrdDir));

        parent::tearDown();
    }

    public function testMissingRrdRendersNoDataImage(): void
    {
        $device = Device::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->get("/graph?device={$device->device_id}&type=device_poller_perf");

        $response->assertStatus(500);
        $response->assertHeader('Content-type', 'image/svg+xml');
        $this->assertStringContainsString('No Data file poller-perf.rrd', $response->getContent());
    }

    public function testRendersGraphFromRrd(): void
    {
        $this->requireRrdtool();
        $device = Device::factory()->create();
        $this->createRrd($device->hostname, 'poller-perf', 'poller');

        $response = $this->actingAs($this->adminUser())
            ->get("/graph?device={$device->device_id}&type=device_poller_perf");

        $response->assertOk();
        $response->assertHeader('Content-type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $response->getContent());
        $this->assertStringNotContainsString('No Data', $response->getContent());
    }

    public function testUserWithoutPermissionGetsNoAuthImage(): void
    {
        $device = Device::factory()->create();
        $user = User::factory()->create(['enabled' => 1]);

        $response = $this->actingAs($user)
            ->get("/graph?device={$device->device_id}&type=device_poller_perf");

        $response->assertStatus(500);
        $this->assertStringContainsString('No Authorization', $response->getContent());
    }

    public function testGuestIsRejected(): void
    {
        $device = Device::factory()->create();

        $response = $this->get("/graph?device={$device->device_id}&type=device_poller_perf");

        $response->assertRedirect('/login');
    }

    public function testUnauthGraphsGuestIsTrusted(): void
    {
        LibrenmsConfig::set('allow_unauth_graphs', true);
        $device = Device::factory()->create();

        $response = $this->get("/graph?device={$device->device_id}&type=device_poller_perf");

        // authorized, so it reaches rrdtool
        $this->assertStringContainsString('No Data', $response->getContent());
        $this->assertStringNotContainsString('No Authorization', $response->getContent());
    }

    public function testSignedUrlGuestIsTrusted(): void
    {
        $device = Device::factory()->create();
        $url = URL::signedRoute('graph', ['device' => $device->device_id, 'type' => 'device_poller_perf']);

        $response = $this->get($url);

        $this->assertStringContainsString('No Data', $response->getContent());
        $this->assertStringNotContainsString('No Authorization', $response->getContent());
    }

    public function testUnknownGraphRendersErrorImage(): void
    {
        $device = Device::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->get("/graph?device={$device->device_id}&type=device_doesnotexist");

        $response->assertStatus(500);
        $response->assertHeader('Content-type', 'image/svg+xml');
        $this->assertStringContainsString('device_doesnotexist template missing', $response->getContent());
    }

    public function testInvalidInputRendersErrorImage(): void
    {
        $device = Device::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->get("/graph?device={$device->device_id}&type=device_poller_perf&legend=bad;value");

        $response->assertStatus(500);
        $response->assertHeader('Content-type', 'image/svg+xml');
        $this->assertStringContainsString('invalid', strtolower($response->getContent()));
    }

    public function testLegendTrueIsAccepted(): void
    {
        $device = Device::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->get("/graph?device={$device->device_id}&type=device_poller_perf&legend=true");

        // passes validation and reaches rrdtool
        $this->assertStringContainsString('No Data', $response->getContent());
    }

    public function testLegacyPathVarsAreSupported(): void
    {
        $device = Device::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->get("/graph/device={$device->device_id}/type=device_poller_perf/");

        $this->assertStringContainsString('No Data file poller-perf.rrd', $response->getContent());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testJpgraphBillGraphRendersImage(): void
    {
        $bill = Bill::factory()->create();
        $now = time();
        $rows = [];
        for ($time = $now - 86400; $time < $now; $time += 300) {
            $rows[] = [
                'bill_id' => $bill->bill_id,
                'timestamp' => date('Y-m-d H:i:s', $time),
                'period' => 300,
                'delta' => 2000,
                'in_delta' => 1000,
                'out_delta' => 1000,
            ];
        }
        DB::table('bill_data')->insert($rows);

        $response = $this->actingAs($this->adminUser())
            ->get('/graph?type=bill_historicbits&id=' . $bill->bill_id . '&from=' . ($now - 86400) . '&to=' . $now . '&width=600&height=300');

        $response->assertOk();
        $response->assertHeader('Content-type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }

    private function adminUser(): User
    {
        return User::factory()->create(['enabled' => 1])->assignRole('admin');
    }

    private function requireRrdtool(): void
    {
        exec('command -v ' . escapeshellarg((string) LibrenmsConfig::get('rrdtool', 'rrdtool')), $output, $code);
        if ($code !== 0) {
            $this->markTestSkipped('rrdtool is not installed');
        }
    }

    private function createRrd(string $hostname, string $name, string $ds): void
    {
        $dir = "$this->rrdDir/$hostname";
        @mkdir($dir);
        $start = time() - 3600;
        exec(sprintf(
            'rrdtool create %s --start %d --step 300 DS:%s:GAUGE:600:U:U RRA:AVERAGE:0.5:1:600 RRA:MIN:0.5:1:600 RRA:MAX:0.5:1:600',
            escapeshellarg("$dir/$name.rrd"), $start, $ds
        ), result_code: $code);
        $this->assertSame(0, $code, 'Failed to create rrd');

        for ($time = $start + 300; $time < time(); $time += 300) {
            exec(sprintf('rrdtool update %s %d:%d', escapeshellarg("$dir/$name.rrd"), $time, random_int(1, 10)));
        }
    }
}

<?php

namespace LibreNMS\Tests\Feature\TimeSeries;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use App\TimeSeries\Contracts\RrdPathResolver;
use App\TimeSeries\Exceptions\InvalidMetric;
use App\TimeSeries\Metrics\Netstats;
use App\TimeSeries\Metrics\ProcessorUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LibreNMS\Data\Store\Rrd;
use LibreNMS\RRD\RrdDefinition;
use LibreNMS\RRD\RrdPath;
use LibreNMS\Tests\TestCase;

/**
 * The rrd store writes rrd_metric to the same file graphs resolve, or fails the write.
 * PollerGraphContractTest checks the pollers pass the metrics the graphs read.
 */
class RrdMetricWriteTest extends TestCase
{
    use RefreshDatabase;

    private string $rrdDir;

    protected function setUp(): void
    {
        parent::setUp();

        exec('command -v rrdtool', $output, $code);
        if ($code !== 0) {
            $this->markTestSkipped('rrdtool is not installed');
        }

        $this->rrdDir = sys_get_temp_dir() . '/librenms-rrd-metric-test-' . uniqid();
        mkdir($this->rrdDir);
        LibrenmsConfig::set('rrd_dir', $this->rrdDir);
        LibrenmsConfig::set('rrdcached', false);
        $this->app->forgetInstance(Rrd::class);
        $this->app->forgetInstance('Datastore');
    }

    protected function tearDown(): void
    {
        if (isset($this->rrdDir)) {
            exec('rm -rf ' . escapeshellarg($this->rrdDir));
        }

        parent::tearDown();
    }

    public function testWritesToTheResolvedFile(): void
    {
        $device = $this->device();
        $metric = new ProcessorUsage($device->device_id, 'hr', 196608);

        $this->write($device, 'processors', ['rrd_name' => ['processor', 'hr', 196608], 'rrd_metric' => $metric], 'usage');

        $this->assertFileExists(app(RrdPathResolver::class)->resolve($metric)->fullPath());
    }

    public function testMetricTakesPrecedenceOverRrdName(): void
    {
        $device = $this->device();

        $this->write($device, 'netstats-tcp', ['rrd_name' => 'something-else', 'rrd_metric' => new Netstats($device->device_id, 'tcp')], 'tcpActiveOpens');

        $this->assertFileExists("$this->rrdDir/$device->hostname/netstats-tcp.rrd");
        $this->assertFileDoesNotExist("$this->rrdDir/$device->hostname/something-else.rrd");
    }

    public function testWithoutMetricUsesRrdName(): void
    {
        $device = $this->device();

        $this->write($device, 'processors', ['rrd_name' => ['processor', 'hr', 3]], 'usage');

        $this->assertFileExists("$this->rrdDir/$device->hostname/processor-hr-3.rrd");
    }

    public function testMetricForAnotherDeviceFailsTheWrite(): void
    {
        $device = $this->device();

        try {
            $this->write($device, 'processors', [
                'rrd_name' => ['processor', 'hr', 1],
                'rrd_metric' => new ProcessorUsage($device->device_id + 1, 'hr', 1),
            ], 'usage');
            $this->fail('Expected InvalidMetric');
        } catch (InvalidMetric) {
            // never falls back to rrd_name
            $this->assertFileDoesNotExist("$this->rrdDir/$device->hostname/processor-hr-1.rrd");
        }
    }

    public function testNonMetricFailsTheWrite(): void
    {
        $device = $this->device();

        try {
            $this->write($device, 'processors', ['rrd_name' => ['processor', 'hr', 2], 'rrd_metric' => 'processor-hr-2'], 'usage');
            $this->fail('Expected InvalidMetric');
        } catch (InvalidMetric) {
            $this->assertFileDoesNotExist("$this->rrdDir/$device->hostname/processor-hr-2.rrd");
        }
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    private function write(Device $device, string $measurement, array $tags, string $field): void
    {
        $tags['rrd_def'] = RrdDefinition::make()->addDataset($field, 'GAUGE', 0);
        app('Datastore')->put($device->toArray(), $measurement, $tags, [$field => 1]);
    }

    private function device(): Device
    {
        $device = Device::factory()->create();
        Rrd::checkDirExists(RrdPath::make($device->hostname)); // as PollDevice does before polling

        return $device;
    }
}

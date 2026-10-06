<?php

namespace LibreNMS\Tests\Feature\TimeSeries;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use App\TimeSeries\Contracts\RrdPathResolver;
use App\TimeSeries\Exceptions\InvalidMetric;
use App\TimeSeries\MetricIdentity;
use App\TimeSeries\MetricSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LibreNMS\Data\Store\Rrd;
use LibreNMS\RRD\RrdDefinition;
use LibreNMS\RRD\RrdPath;
use LibreNMS\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Graphs read metrics through the MetricSchema, pollers write them with their own names.
 * Each schema entry writes a real rrd file exactly as its poller does, then checks the graph side resolves to it.
 */
class MetricWriterContractTest extends TestCase
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

        $this->rrdDir = sys_get_temp_dir() . '/librenms-contract-test-' . uniqid();
        mkdir($this->rrdDir);
        LibrenmsConfig::set('rrd_dir', $this->rrdDir);
        LibrenmsConfig::set('rrdcached', false);
        $this->app->forgetInstance(Rrd::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->rrdDir)) {
            exec('rm -rf ' . escapeshellarg($this->rrdDir));
        }

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: array<string, scalar>, 2: string, 3: array<string, mixed>, 4: string}>
     */
    public static function writers(): array
    {
        return [
            // LibreNMS\Device\Processor::poll()
            'processor' => ['processor', ['processor_type' => 'hr', 'processor_index' => 196608], 'processors', ['rrd_name' => ['processor', 'hr', 196608]], 'usage'],
            // LibreNMS\Modules\Netstats::poll()
            'netstats-ip' => ['netstats-ip', [], 'netstats-ip', [], 'ipInReceives'],
        ];
    }

    /**
     * @param  array<string, scalar>  $labels
     * @param  array<string, mixed>  $meta
     */
    #[DataProvider('writers')]
    public function testGraphsReadWhatPollersWrite(string $metric, array $labels, string $measurement, array $meta, string $field): void
    {
        $device = Device::factory()->create();

        Rrd::checkDirExists(RrdPath::make($device->hostname)); // as PollDevice does before polling

        $rrdDef = RrdDefinition::make()->addDataset($field, 'GAUGE', 0);
        app(Rrd::class)->write($measurement, [$field => 1], [], ['device' => $device, 'rrd_def' => $rrdDef, ...$meta]);

        $path = app(RrdPathResolver::class)->resolve(new MetricIdentity($metric, ['device_id' => $device->device_id, ...$labels]));

        $this->assertFileExists($path->fullPath());
    }

    public function testEverySchemaMetricHasAContract(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(self::writers()), app(MetricSchema::class)->names());
    }

    public function testUnregisteredMetricsCannotBeResolved(): void
    {
        $device = Device::factory()->create();

        $this->expectException(InvalidMetric::class);

        app(RrdPathResolver::class)->resolve(new MetricIdentity('not-registered', ['device_id' => $device->device_id]));
    }
}

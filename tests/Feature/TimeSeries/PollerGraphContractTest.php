<?php

namespace LibreNMS\Tests\Feature\TimeSeries;

use App\Facades\DeviceCache;
use App\Graphing\Contracts\Graph;
use App\Graphing\GraphQuery;
use App\Graphs\Device\NetstatIpGraph;
use App\Graphs\Device\ProcessorGraph;
use App\Models\Device;
use App\Models\Processor;
use App\TimeSeries\Contracts\RrdPathResolver;
use App\TimeSeries\Metric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LibreNMS\Device\Processor as ProcessorPoller;
use LibreNMS\Interfaces\Data\DataStorageInterface;
use LibreNMS\Interfaces\Polling\ProcessorPolling;
use LibreNMS\Modules\Netstats;
use LibreNMS\OS;
use LibreNMS\Tests\TestCase;
use Mockery;

/**
 * Runs the real poller code and checks it writes to the files the graph classes read.
 */
class PollerGraphContractTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{measurement: string, tags: array<string, mixed>}> */
    private array $writes = [];

    public function testNetstatsIp(): void
    {
        $device = Device::factory()->create();

        (new Netstats)->poll($this->os($device, netstats: ['IP-MIB::ipInReceives.0' => 10]), $this->datastore());

        $this->assertWritesMatchGraph(new NetstatIpGraph, $device, 'device_netstat_ip');
    }

    public function testProcessors(): void
    {
        $device = Device::factory()->create();
        $processors = Processor::factory()->for($device)->count(2)->create();

        $this->app->instance('Datastore', $this->datastore());
        ProcessorPoller::poll($this->os($device, processors: $processors->mapWithKeys(fn ($p) => [$p->processor_id => 50])->all()));

        $this->assertWritesMatchGraph(new ProcessorGraph, $device, 'device_processor');
    }

    private function assertWritesMatchGraph(Graph $graph, Device $device, string $type): void
    {
        $resolver = app(RrdPathResolver::class);

        $written = array_map(function (array $write) use ($resolver) {
            $this->assertInstanceOf(Metric::class, $write['tags']['rrd_metric'] ?? null, "{$write['measurement']} was written without rrd_metric");

            return $resolver->resolve($write['tags']['rrd_metric'])->relativePath();
        }, $this->writes);

        $query = GraphQuery::fromVars(['type' => $type, 'device' => $device->device_id]);
        $read = array_map(
            fn ($series) => $resolver->resolve($series->metric)->relativePath(),
            $graph->define($graph->subject($query), $query)->series,
        );

        $this->assertNotEmpty($read);
        $this->assertEqualsCanonicalizing(array_values(array_unique($read)), array_values(array_unique(array_intersect($written, $read))));
    }

    private function datastore(): DataStorageInterface
    {
        $datastore = Mockery::mock(DataStorageInterface::class);
        $datastore->shouldReceive('put')->andReturnUsing(function ($device, $measurement, $tags): void {
            $this->writes[] = ['measurement' => $measurement, 'tags' => $tags];
        });

        return $datastore;
    }

    /**
     * @param  array<string, int>  $netstats  oid => value for ip netstats
     * @param  array<int, int>  $processors  processor_id => usage
     */
    private function os(Device $device, array $netstats = [], array $processors = []): OS
    {
        DeviceCache::setPrimary($device->device_id);
        $deviceArray = $device->toArray();

        return new class($deviceArray, $netstats, $processors) extends OS implements ProcessorPolling
        {
            /**
             * @param  array<mixed>  $device
             * @param  array<string, int>  $netstats
             * @param  array<int, int>  $processors
             */
            public function __construct(array &$device, private readonly array $netstats, private readonly array $processors)
            {
                parent::__construct($device);
            }

            /**
             * @param  array<int, string>  $oids
             * @return array<string, int>
             */
            public function pollIpNetstats(array $oids): array
            {
                return $this->netstats;
            }

            /**
             * @param  array<int, string>  $oids
             * @return array<string, int>
             */
            public function pollIcmpNetstats(array $oids): array
            {
                return [];
            }

            /**
             * @param  array<int, string>  $oids
             * @return array<string, int>
             */
            public function pollSnmpNetstats(array $oids): array
            {
                return [];
            }

            /**
             * @param  array<int, string>  $oids
             * @return array<string, int>
             */
            public function pollIpForwardNetstats(array $oids): array
            {
                return [];
            }

            /**
             * @param  array<int, string>  $oids
             * @return array<string, int>
             */
            public function pollUdpNetstats(array $oids): array
            {
                return [];
            }

            /**
             * @param  array<int, string>  $oids
             * @return array<string, int>
             */
            public function pollTcpNetstats(array $oids): array
            {
                return [];
            }

            /**
             * @param  array<int, array<string, mixed>>  $processors
             * @return array<int, int>
             */
            public function pollProcessors(array $processors)
            {
                return $this->processors;
            }
        };
    }
}

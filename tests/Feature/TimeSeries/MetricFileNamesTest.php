<?php

namespace LibreNMS\Tests\Feature\TimeSeries;

use App\Models\Device;
use App\TimeSeries\Contracts\RrdPathResolver;
use App\TimeSeries\Metric;
use App\TimeSeries\Metrics\Netstats;
use App\TimeSeries\Metrics\ProcessorUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LibreNMS\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Metric names and label order are a storage format.
 * Changing them renames rrd files and orphans existing data, these pins make that visible.
 * Add a case for every class in App\TimeSeries\Metrics.
 */
class MetricFileNamesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: callable(int): Metric, 1: string}>
     */
    public static function metrics(): array
    {
        return [
            'processor' => [fn (int $id) => new ProcessorUsage($id, 'hr', 196608), 'processor-hr-196608.rrd'],
            'netstats icmp' => [fn (int $id) => new Netstats($id, 'icmp'), 'netstats-icmp.rrd'],
            'netstats ip' => [fn (int $id) => new Netstats($id, 'ip'), 'netstats-ip.rrd'],
            'netstats ip_forward' => [fn (int $id) => new Netstats($id, 'ip_forward'), 'netstats-ip_forward.rrd'],
            'netstats snmp' => [fn (int $id) => new Netstats($id, 'snmp'), 'netstats-snmp.rrd'],
            'netstats tcp' => [fn (int $id) => new Netstats($id, 'tcp'), 'netstats-tcp.rrd'],
            'netstats udp' => [fn (int $id) => new Netstats($id, 'udp'), 'netstats-udp.rrd'],
        ];
    }

    /**
     * @param  callable(int): Metric  $metric
     */
    #[DataProvider('metrics')]
    public function testFileName(callable $metric, string $file): void
    {
        $device = Device::factory()->create(['hostname' => 'host.example.com']);

        $this->assertSame("host.example.com/$file", app(RrdPathResolver::class)->resolve($metric($device->device_id))->relativePath());
    }

    public function testEveryMetricClassIsPinned(): void
    {
        $pinned = array_unique(array_map(fn ($case) => $case[0](1)::class, self::metrics()));
        $classes = array_map(
            fn (string $file) => 'App\\TimeSeries\\Metrics\\' . basename($file, '.php'),
            glob(base_path('app/TimeSeries/Metrics/*.php')) ?: [],
        );

        $this->assertEqualsCanonicalizing($classes, array_values($pinned));
    }

    public function testUnknownNetstatsType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Netstats(1, 'nope');
    }
}

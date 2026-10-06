<?php

namespace LibreNMS\Tests\Feature\TimeSeries;

use App\Facades\DeviceCache;
use App\Models\Device;
use App\TimeSeries\Contracts\RrdPathResolver;
use App\TimeSeries\Exceptions\InvalidMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LibreNMS\Tests\Mocks\FakeMetric;
use LibreNMS\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The resolver is the only place metric file paths are built, it owns their safety.
 */
class RrdPathResolverTest extends TestCase
{
    use RefreshDatabase;

    public function testOrderIsNameThenLabelsAsDeclared(): void
    {
        $device = Device::factory()->create(['hostname' => 'host.example.com']);

        $path = $this->resolve(new FakeMetric('m', ['z' => 'last', 'a' => 'first'], $device->device_id));

        $this->assertSame('host.example.com/m-last-first.rrd', $path);
    }

    public function testComponentsAreEscaped(): void
    {
        $device = Device::factory()->create(['hostname' => '[2001:db8::1]']);

        $path = $this->resolve(new FakeMetric('app/x y', ['path' => '../../etc', 'n' => 3], $device->device_id));

        $this->assertSame('2001_db8__1/app_x_y-.._.._etc-3.rrd', $path);
        $this->assertStringNotContainsString('/', substr($path, strpos($path, '/') + 1), 'no separator in the file name');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeHostnames(): array
    {
        return [
            'empty' => [''],
            'dot' => ['.'],
            'dot dot' => ['..'],
            'bracketed dot dot' => ['[..]'],
        ];
    }

    #[DataProvider('unsafeHostnames')]
    public function testUnsafeHostnamesAreRejected(string $hostname): void
    {
        $device = Device::factory()->create();
        $device->hostname = $hostname; // only in the cache, the database would not accept it
        DeviceCache::fake($device);

        $this->expectException(InvalidMetric::class);

        $this->resolve(new FakeMetric('m', [], $device->device_id));
    }

    public function testEmptyNameIsRejected(): void
    {
        $device = Device::factory()->create();

        $this->expectException(InvalidMetric::class);

        $this->resolve(new FakeMetric('', [], $device->device_id));
    }

    public function testEmptyLabelIsRejected(): void
    {
        $device = Device::factory()->create();

        $this->expectException(InvalidMetric::class);
        $this->expectExceptionMessage('label index cannot be empty');

        $this->resolve(new FakeMetric('processor', ['type' => 'hr', 'index' => ''], $device->device_id));
    }

    public function testZeroIsAValidLabel(): void
    {
        $device = Device::factory()->create(['hostname' => 'host']);

        $this->assertSame('host/processor-hr-0.rrd', $this->resolve(new FakeMetric('processor', ['type' => 'hr', 'index' => 0], $device->device_id)));
    }

    private function resolve(FakeMetric $metric): string
    {
        return app(RrdPathResolver::class)->resolve($metric)->relativePath();
    }
}

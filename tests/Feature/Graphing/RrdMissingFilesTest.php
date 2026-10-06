<?php

namespace LibreNMS\Tests\Feature\Graphing;

use App\Facades\LibrenmsConfig;
use LibreNMS\Data\Store\Rrd;
use LibreNMS\RRD\RrdPath;
use LibreNMS\Tests\TestCase;

class RrdMissingFilesTest extends TestCase
{
    private string $rrdDir;
    private ?string $pidFile = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rrdDir = sys_get_temp_dir() . '/librenms-missing-test-' . uniqid();
        mkdir("$this->rrdDir/host1", 0777, true);
        touch("$this->rrdDir/host1/a.rrd");
        touch("$this->rrdDir/host1/b-x.rrd");

        LibrenmsConfig::set('rrd_dir', $this->rrdDir);
        LibrenmsConfig::set('rrdtool_version', '1.7');
        LibrenmsConfig::set('rrdcached', false);
    }

    protected function tearDown(): void
    {
        if ($this->pidFile && is_file($this->pidFile)) {
            posix_kill((int) file_get_contents($this->pidFile), SIGTERM);
            usleep(200000);
        }
        exec('rm -rf ' . escapeshellarg($this->rrdDir));

        parent::tearDown();
    }

    public function testLocal(): void
    {
        $this->assertMissing(['host1/c.rrd', 'host2/a.rrd'], $this->rrd()->missingFiles($this->paths()));
    }

    public function testRrdcached(): void
    {
        exec('command -v rrdcached', $output, $code);
        if ($code !== 0) {
            $this->markTestSkipped('rrdcached is not installed');
        }

        mkdir("$this->rrdDir/journal");
        $socket = "$this->rrdDir/rrdcached.sock";
        $this->pidFile = "$this->rrdDir/rrdcached.pid";
        exec(sprintf('rrdcached -l unix:%s -b %s -B -p %s -j %s',
            escapeshellarg($socket), escapeshellarg($this->rrdDir), escapeshellarg($this->pidFile), escapeshellarg("$this->rrdDir/journal")));
        for ($i = 0; $i < 20 && ! file_exists($socket); $i++) {
            usleep(100000);
        }

        LibrenmsConfig::set('rrdcached', "unix:$socket");

        $this->assertMissing(['host1/c.rrd', 'host2/a.rrd'], $this->rrd()->missingFiles($this->paths()));
    }

    /**
     * @return list<RrdPath>
     */
    private function paths(): array
    {
        return [
            RrdPath::make('host1', 'a.rrd'),
            RrdPath::make('host1', 'b-x.rrd'),
            RrdPath::make('host1', 'c.rrd'),
            RrdPath::make('host2', 'a.rrd'), // directory does not exist
        ];
    }

    /**
     * @param  list<string>  $expected
     * @param  list<RrdPath>  $missing
     */
    private function assertMissing(array $expected, array $missing): void
    {
        $this->assertSame($expected, array_map(fn (RrdPath $path) => $path->relativePath(), $missing));
    }

    private function rrd(): Rrd
    {
        $this->app->forgetInstance(Rrd::class);

        return app(Rrd::class);
    }
}

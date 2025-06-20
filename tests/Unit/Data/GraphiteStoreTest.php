<?php

/**
 * GraphiteStoreTest.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Tests\Unit\Data;

use Carbon\Carbon;
use LibreNMS\Config;
use LibreNMS\Data\Store\Graphite;
use LibreNMS\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use Socket\Raw\Socket;

#[Group('datastores')]
class GraphiteStoreTest extends TestCase
{
    protected int $timestamp = 1197464400;

    protected function setUp(): void
    {
        parent::setUp();

        // fix the date
        Carbon::setTestNow(Carbon::createFromTimestampUTC($this->timestamp));
        Config::set('graphite.enable', true);
    }

    protected function tearDown(): void
    {
        // restore Carbon:now() to normal
        Carbon::setTestNow();
        Config::set('graphite.enable', false);

        parent::tearDown();
    }

    public function testSocketConnectError(): void
    {
        $mockFactory = \Mockery::mock(\Socket\Raw\Factory::class);

        $mockFactory->shouldReceive('createClient')
            ->andThrow('Socket\Raw\Exception', 'Failed to handle connect exception')->once();

        new Graphite($mockFactory);
    }

    public function testSocketWriteError(): void
    {
        $mockSocket = \Mockery::mock(Socket::class);
        $graphite = $this->mockGraphite($mockSocket);

        $mockSocket->shouldReceive('write')
            ->andThrow('Socket\Raw\Exception', 'Did not handle socket exception')->once();

        $graphite->write('fake', ['one' => 1], ['hostname' => 'testhost']);
    }

    public function testSimpleWrite(): void
    {
        Config::set('graphite.prefix', 'librenms');
        $mockSocket = \Mockery::mock(Socket::class);
        $graphite = $this->mockGraphite($mockSocket);

        $measurement = 'testmeasure';
        $tags = ['ifName' => 'testifname', 'type' => 'testtype'];
        $fields = ['ifIn' => 234234, 'ifOut' => 53453];

        $mockSocket->shouldReceive('write')
            ->with("librenms.testmeasure.ifIn;ifName=testifname;type=testtype 234234 $this->timestamp\n")->once();
        $mockSocket->shouldReceive('write')
            ->with("librenms.testmeasure.ifOut;ifName=testifname;type=testtype 53453 $this->timestamp\n")->once();
        $graphite->write($measurement, $fields, $tags);

        Config::set('graphite.prefix', null);
    }

    public function testWriteOnlyTags(): void
    {
        $mockSocket = \Mockery::mock(Socket::class);
        $graphite = $this->mockGraphite($mockSocket);

        $measurement = 'testmeasure';
        $tags = ['hostname' => 'testhost', 'ifName' => 'testifname', 'type' => 'testtype'];
        $fields = ['ifIn' => 234234, 'ifOut' => 53453];

        $mockSocket->shouldReceive('write')
            ->with("testmeasure.ifIn;hostname=testhost;ifName=testifname;type=testtype 234234 $this->timestamp\n")->once();
        $mockSocket->shouldReceive('write')
            ->with("testmeasure.ifOut;hostname=testhost;ifName=testifname;type=testtype 53453 $this->timestamp\n")->once();
        $graphite->write($measurement, $fields, $tags);
    }


    private function mockGraphite(Socket $mockSocket): Graphite
    {
        $mockFactory = \Mockery::mock(\Socket\Raw\Factory::class);

        $mockFactory->shouldReceive('createClient')
            ->andReturn($mockSocket);

        return new Graphite($mockFactory);
    }
}

<?php

namespace LibreNMS\Tests;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use LibreNMS\Data\Store\Rrd;
use LibreNMS\Exceptions\RrdGraphException;
use LibreNMS\Exceptions\RrdNotFoundException;
use LibreNMS\RRD\RrdDefinition;
use LibreNMS\RRD\RrdProcess;

final class RrdtoolTest extends TestCase
{
    public function testRrdConstructorInjection(): void
    {
        $mock = $this->createMock(RrdProcess::class);
        $rrd = new Rrd($mock);

        $this->assertSame($mock, (fn () => $this->rrd)->call($rrd));
    }

    public function testWriteWithCreate(): void
    {
        $mock = $this->createMock(RrdProcess::class);
        $rrd = new Rrd($mock);

        $meta = [
            'rrd_def' => RrdDefinition::make()->addDataset('DS1', 'GAUGE'),
            'device' => $this->createMock(Device::class),
        ];

        // First call fails with RrdNotFoundException to trigger create
        $matcher = $this->exactly(3);
        $mock->expects($matcher)
            ->method('run')
            ->willReturnCallback(function ($command) use ($matcher) {
                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertStringContainsString('update', $command);
                    throw new RrdNotFoundException('not found');
                } elseif ($matcher->numberOfInvocations() === 2) {
                    $this->assertStringContainsString('create', $command);
                } elseif ($matcher->numberOfInvocations() === 3) {
                    $this->assertStringContainsString('update', $command);
                }

                return 'OK';
            });

        $rrd->write('test', ['DS1' => 1], [], $meta);
    }

    public function testUpdate(): void
    {
        $mock = $this->createMock(RrdProcess::class);
        $mock->expects($this->once())
            ->method('run')
            ->with('update filename.rrd N:1:2:U')
            ->willReturn('OK');

        $rrd = new Rrd($mock);
        $rrd->update('filename.rrd', [1, '2', 'a']);
    }

    public function testTune(): void
    {
        $mock = $this->createMock(RrdProcess::class);
        $mock->expects($this->once())
            ->method('run')
            ->with($this->stringContains('tune filename.rrd --maximum INOCTETS:1250000'))
            ->willReturn('OK');

        $rrd = new Rrd($mock);
        $this->assertTrue($rrd->tune('port', 'filename.rrd', 10000000));
    }

    public function testLastUpdate(): void
    {
        $mockOutput = " INOCTETS OUTOCTETS\n\n1616789000: 12345 67890\nOK";
        $mock = $this->createMock(RrdProcess::class);
        $mock->expects($this->once())
            ->method('run')
            ->with('lastupdate filename.rrd')
            ->willReturn($mockOutput);

        $rrd = new Rrd($mock);
        $point = $rrd->lastUpdate('filename.rrd');

        $this->assertNotNull($point);
        $this->assertEquals(1616789000, $point->timestamp);
        $this->assertEquals(['INOCTETS' => '12345', 'OUTOCTETS' => '67890'], $point->data);
    }

    public function testGraph(): void
    {
        $mock = $this->createMock(RrdProcess::class);
        $mock->expects($this->once())
            ->method('run')
            ->with('"graph" "-" "option1" "option2"')
            ->willReturn('BINARY_DATA');
        $mock->expects($this->atLeastOnce())->method('stop');

        $rrd = new Rrd($mock);
        $result = $rrd->graph(['option1', 'option2']);
        $this->assertEquals('BINARY_DATA', $result);
    }

    public function testGraphException(): void
    {
        $mock = $this->createMock(RrdProcess::class);
        $mock->method('run')->willThrowException(new \LibreNMS\Exceptions\RrdException('rrd error'));

        $rrd = new Rrd($mock);
        $this->expectException(RrdGraphException::class);
        $rrd->graph(['options']);
    }

    public function testBuildCommandVersionO(): void
    {
        $mock = $this->createMock(RrdProcess::class);
        $rrd = new Rrd($mock);

        $meta = [
            'rrd_def' => RrdDefinition::make()->addDataset('DS1', 'GAUGE'),
            'device' => $this->createMock(Device::class),
        ];

        // Version 1.4.3 should have -O in create command
        LibrenmsConfig::set('rrdtool_version', '1.4.3');
        $matcher = $this->exactly(3);
        $mock->expects($matcher)
            ->method('run')
            ->willReturnCallback(function ($command) use ($matcher) {
                $count = $matcher->numberOfInvocations();
                if ($count === 1) {
                    throw new RrdNotFoundException('not found');
                }
                if ($count === 2) {
                    $this->assertStringContainsString('create', $command);
                    $this->assertStringContainsString('-O', $command);
                }

                return 'OK';
            });

        $rrd->write('test', ['DS1' => 1], [], $meta);

        // Version 1.4.2 should NOT have -O in create command
        LibrenmsConfig::set('rrdtool_version', '1.4.2');
        $mock = $this->createMock(RrdProcess::class);
        $matcher = $this->exactly(3);
        $mock->expects($matcher)
            ->method('run')
            ->willReturnCallback(function ($command) use ($matcher) {
                $count = $matcher->numberOfInvocations();
                if ($count === 1) {
                    throw new RrdNotFoundException('not found');
                }
                if ($count === 2) {
                    $this->assertStringContainsString('create', $command);
                    $this->assertStringNotContainsString('-O', $command);
                }

                return 'OK';
            });

        $rrd = new Rrd($mock);
        $rrd->write('test', ['DS1' => 1], [], $meta);
    }
}

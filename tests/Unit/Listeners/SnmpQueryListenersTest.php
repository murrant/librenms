<?php

namespace LibreNMS\Tests\Unit\Listeners;

use App\Events\SnmpQueryExecuted;
use App\Listeners\CheckSnmpExitCode;
use App\Listeners\PrintSnmpDebugOutput;
use App\Models\Device;
use Illuminate\Support\Facades\Log;
use LibreNMS\Data\Source\Snmp\SnmpDebugInfo;
use LibreNMS\Enum\Severity;
use LibreNMS\Tests\TestCase;
use LibreNMS\Util\Debug;

class SnmpQueryListenersTest extends TestCase
{
    public function testCheckSnmpExitCodeLogsUnsupportedAuth(): void
    {
        $device = Device::factory()->create();
        $debugInfo = new SnmpDebugInfo(
            command: ['snmpget', 'sysDescr.0'],
            exitCode: 1,
            stderr: "Invalid authentication protocol specified\n",
        );
        $event = new SnmpQueryExecuted(
            method: 'snmpget',
            oids: ['sysDescr.0'],
            device: $device,
            debugInfo: $debugInfo,
        );

        $listener = new CheckSnmpExitCode();
        $listener->handle($event);

        $this->assertDatabaseHas('eventlog', [
            'device_id' => $device->device_id,
            'message' => 'Unsupported SNMP authentication algorithm - 1',
            'type' => 'poller',
            'severity' => Severity::Error->value,
        ]);
    }

    public function testPrintSnmpDebugOutputWithDebugEnabled(): void
    {
        Debug::set();

        Log::shouldReceive('debug')
            ->atLeast()->once();

        $device = Device::factory()->create();
        $debugInfo = new SnmpDebugInfo(
            command: ['/usr/bin/snmpget', '-c', 'public', 'sysDescr.0'],
            exitCode: 0,
            output: "sysDescr.0 = Linux 6.0\n",
        );
        $event = new SnmpQueryExecuted(
            method: 'snmpget',
            oids: ['sysDescr.0'],
            device: $device,
            debugInfo: $debugInfo,
        );

        $listener = new PrintSnmpDebugOutput();
        $listener->handle($event);

        Debug::set(false);
    }
}

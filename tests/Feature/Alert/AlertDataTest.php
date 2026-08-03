<?php

namespace LibreNMS\Tests\Feature\Alert;

use App\Facades\Rrd;
use App\Models\AlertLog;
use App\Models\AlertRule;
use App\Models\AlertTemplate;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LibreNMS\Alert\AlertData;
use LibreNMS\Enum\AlertState;
use LibreNMS\Tests\TestCase;

final class AlertDataTest extends TestCase
{
    use RefreshDatabase;

    public function testAlertLogDetailsAreCompressedAndCastedDuringModelUpdate(): void
    {
        $alertLog = AlertLog::factory()->create(['details' => ['count' => 3]]);
        $details = [
            'count' => 0,
            'rule' => [['port_id' => 42, 'ifDescr' => 'eth0']],
        ];

        $alertLog->update(['details' => $details]);

        $rawDetails = DB::table('alert_log')->where('id', $alertLog->id)->value('details');
        $this->assertIsString($rawDetails);
        $this->assertNotSame(json_encode($details), $rawDetails);
        $this->assertSame($details, json_decode(gzuncompress($rawDetails), true));
        $this->assertSame($details, $alertLog->fresh()->details);
    }

    public function testDescribeRecoveredAlertUpdatesPreviousLogThroughItsDetailsCast(): void
    {
        Rrd::shouldReceive('name')->zeroOrMoreTimes()->andReturn('unused');
        Rrd::shouldReceive('lastUpdate')->zeroOrMoreTimes()->andReturnNull();

        $device = Device::factory()->create();
        $rule = AlertRule::factory()->create();
        $template = AlertTemplate::factory()->create([
            'name' => 'Default Alert Template',
            'title_rec' => 'Recovered title',
        ]);
        $previousDetails = [
            'count' => 4,
            'contacts' => ['admin@example.com' => 'Admin'],
            'rule' => [['port_id' => 42, 'ifDescr' => 'eth0']],
        ];
        $previous = AlertLog::factory()->create([
            'device_id' => $device->device_id,
            'rule_id' => $rule->id,
            'state' => AlertState::ACTIVE,
            'details' => $previousDetails,
            'time_logged' => now()->subMinutes(5),
        ]);
        $recovered = AlertLog::factory()->create([
            'device_id' => $device->device_id,
            'rule_id' => $rule->id,
            'state' => AlertState::RECOVERED,
            'details' => ['count' => 9, 'marker' => 'current log'],
            'time_logged' => now(),
        ]);

        $data = AlertData::describe([
            'id' => $recovered->id,
            'alert_id' => 123,
            'device_id' => $device->device_id,
            'rule_id' => $rule->id,
            'state' => AlertState::RECOVERED,
            'details' => $recovered->details,
            'time_logged' => $recovered->time_logged,
            'severity' => $rule->severity,
            'name' => $rule->name,
            'builder' => '{}',
            'proc' => $rule->proc,
            'note' => $rule->notes,
            'alerted' => AlertState::ACTIVE,
        ]);

        $expectedDetails = [...$previousDetails, 'count' => 0];
        $this->assertSame($previous->id, $data['id']);
        $this->assertSame($template->title_rec, $data['title']);
        $this->assertSame($previousDetails['contacts'], $data['contacts']);
        $this->assertSame('port_id => 42; ifDescr => eth0; ', $data['faults'][1]['string']);
        $this->assertSame($expectedDetails, $previous->fresh()->details);
        $this->assertSame(['count' => 9, 'marker' => 'current log'], $recovered->fresh()->details);

        $rawDetails = DB::table('alert_log')->where('id', $previous->id)->value('details');
        $this->assertSame($expectedDetails, json_decode(gzuncompress($rawDetails), true));
    }
}

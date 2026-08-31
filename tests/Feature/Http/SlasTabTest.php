<?php

namespace LibreNMS\Tests\Feature\Http;

use App\Models\Device;
use App\Models\Sla;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LibreNMS\Tests\TestCase;
use Spatie\Permission\Models\Role;

class SlasTabTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('user');
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['enabled' => 1]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function testAuthorizedUserCanRenderSlasList(): void
    {
        $device = Device::factory()->create();
        Sla::factory()->for($device)->create([
            'sla_nr' => 10,
            'owner' => 'NetAdmin',
            'tag' => 'Primary-WAN-Check',
            'rtt_type' => 'jitter',
            'opstatus' => 0,
            'deleted' => 0,
        ]);

        $this->actingAs($this->admin())
            ->get(route('device', ['device' => $device, 'tab' => 'slas']))
            ->assertOk()
            ->assertSee('SLA #10 - Jitter')
            ->assertSee('Primary-WAN-Check')
            ->assertSee('(Owner: NetAdmin)')
            ->assertSee('device_sla');
    }

    public function testAuthorizedUserCanRenderIcmpEchoSla(): void
    {
        $device = Device::factory()->create();
        $sla = Sla::factory()->for($device)->create([
            'sla_nr' => 15,
            'owner' => 'PingTeam',
            'tag' => 'Gateway-Ping',
            'rtt_type' => 'icmpEcho',
            'opstatus' => 0,
            'deleted' => 0,
        ]);

        $this->actingAs($this->admin())
            ->get(route('device', ['device' => $device, 'tab' => 'slas']))
            ->assertOk()
            ->assertSee('SLA #15 - ICMP Echo');

        $this->actingAs($this->admin())
            ->get(route('device', ['device' => $device, 'tab' => 'slas', 'id' => $sla->sla_id]))
            ->assertOk()
            ->assertSee('SLA #15 - ICMP Echo')
            ->assertSee('Round Trip Time')
            ->assertSee('Packet Loss');
    }

    public function testAuthorizedUserCanRenderSlaDetails(): void
    {
        $device = Device::factory()->create();
        $sla = Sla::factory()->for($device)->create([
            'sla_nr' => 20,
            'owner' => 'VoIPTeam',
            'tag' => 'VoIP-Jitter-Monitor',
            'rtt_type' => 'jitter',
            'opstatus' => 2,
            'deleted' => 0,
        ]);

        $this->actingAs($this->admin())
            ->get(route('device', ['device' => $device, 'tab' => 'slas', 'id' => $sla->sla_id]))
            ->assertOk()
            ->assertSee('SLA #20 - Jitter')
            ->assertSee('VoIP-Jitter-Monitor')
            ->assertSee('Average Latency One Way')
            ->assertSee('Average Jitter')
            ->assertSee('Packet Loss (Percent)')
            ->assertSee('Mean Opinion Score');
    }

    public function testUserWithoutAccessGetsForbidden(): void
    {
        $device = Device::factory()->create();
        Sla::factory()->for($device)->create();

        $user = User::factory()->create(['enabled' => 1]);
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('device', ['device' => $device, 'tab' => 'slas']))
            ->assertForbidden();
    }
}

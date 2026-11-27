<?php

namespace LibreNMS\Tests\Feature\Modules;

use App\Facades\DeviceCache;
use App\Facades\LibrenmsConfig;
use App\Models\Device;
use App\Models\DevicePollingMethod;
use App\Models\Secret;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LibreNMS\Enum\PollingMethodType;
use LibreNMS\Enum\SecretType;
use LibreNMS\Interfaces\Data\DataStorageInterface;
use LibreNMS\Interfaces\Polling\PortDataSource;
use LibreNMS\Modules\Ports;
use LibreNMS\OS;
use LibreNMS\OS\Bison;
use LibreNMS\Polling\Method\PollingMethodRegistry;
use LibreNMS\Ports\PortInventory;
use LibreNMS\Tests\InMemoryDbTestCase;
use LibreNMS\Util\Module;
use Mockery;

final class PortsTest extends InMemoryDbTestCase
{
    public function testDiscoveryReusesPortIdsAndOnlyRetiresAfterANonemptyInventory(): void
    {
        $this->artisan('config:set', ['setting' => 'modules.ports.version', 'value' => '2'])->assertExitCode(0);
        LibrenmsConfig::set('default_port_association_mode', 'ifName');
        $device = Device::factory()->create(['os' => 'generic']);
        $os = $this->os($device);
        $one = ['ifName' => 'eth1', 'ifDescr' => 'Ethernet one'];
        $two = ['ifName' => 'eth2', 'ifDescr' => 'Ethernet two'];
        $os->expects('discoverPorts')->times(5)->andReturn(
            new PortInventory([1 => $one, 2 => $two], association: 'ifName'), new PortInventory([]),
            new PortInventory([1 => $one], complete: false, association: 'ifName'),
            new PortInventory([2 => $one], association: 'ifName'), new PortInventory([2 => $one, 1 => $two], association: 'ifName'),
        );
        $module = Module::fromName('ports');
        $this->assertInstanceOf(Ports::class, $module);
        $module->discover($os);
        $ids = $device->ports()->pluck('port_id', 'ifName');
        $device->setAttrib('ifName:eth1', '1');
        $device->ports()->whereKey($ids['eth1'])->update(['ifAlias' => 'Local alias']);
        $module->discover($os);
        $this->assertEquals(0, $device->ports()->sum('deleted'));
        $module->discover($os);
        $this->assertEquals(0, $device->ports()->sum('deleted'));
        $module->discover($os);
        $this->assertDatabaseHas('ports', ['port_id' => $ids['eth1'], 'ifIndex' => 2, 'deleted' => 0, 'ifAlias' => 'Local alias']);
        $this->assertDatabaseHas('ports', ['port_id' => $ids['eth2'], 'deleted' => 1]);
        $module->discover($os);
        $this->assertDatabaseHas('ports', ['port_id' => $ids['eth2'], 'ifIndex' => 1, 'deleted' => 0]);
        $this->assertEquals(2, $device->ports()->count());
    }

    public function testPollingUsesExistingPortsAndProducesConsecutiveCounterSamples(): void
    {
        $this->freezeTime();
        LibrenmsConfig::set('modules.ports.version', 2);
        $device = Device::factory()->create(['os' => 'generic']);
        $device->setAttrib('selected_ports', 'true');
        $device->setAttrib('ifName:eth1', 'Transit: Example [10G]');
        $port = $device->ports()->create(['ifIndex' => 1, 'ifName' => 'eth1']);
        $other = $device->ports()->create(['ifIndex' => 2, 'ifName' => 'eth2', 'disabled' => 1]);
        $row = ['ifName' => 'eth1', 'ifDescr' => 'uplink', 'ifSpeed' => 4294967295, 'ifHighSpeed' => 10000,
            'ifPhysAddress' => '00 01 02 03 04 05', 'ifOperStatus' => 'up', 'ifHCInOctets' => '0', 'ifInOctets' => '9999', 'ifInDiscards' => '0'];
        $os = $this->os($device);
        new PortInventory([1 => $row, 2 => ['ifName' => 'eth2', 'ifDescr' => 'eth2']])->sync($device);
        $os->expects('pollPorts')->with(Mockery::on(fn ($ports) => array_keys($ports) === [1]), true)->times(4)->andReturn(
            [1 => $row], [1 => ['ifHCInOctets' => '900', 'ifInDiscards' => '6'] + $row],
            [1 => ['ifHCInOctets' => '900', 'ifInDiscards' => '6'] + $row], [1 => $row],
        );
        $values = [];
        $store = Mockery::mock(DataStorageInterface::class);
        $store->expects('put')->times(4)->andReturnUsing(function ($device, $name, $tags, $fields) use (&$values, $port): void {
            $this->assertSame('ports', $name);
            $this->assertSame('port-id' . $port->port_id, $tags['rrd_name']);
            $values[] = $fields['INOCTETS'];
        });
        $module = new Ports;
        $module->poll($os, $store);
        $this->assertNull($port->fresh()->ifInOctets_rate);
        $this->travel(300)->seconds();
        $module->poll($os, $store);
        $port->refresh();
        $this->assertEquals(3, $port->ifInOctets_rate);
        $this->assertEquals(6, $port->statistics()->sole()->ifInDiscards_delta);
        $this->assertEquals(10000000000, $port->ifSpeed);
        $this->assertSame('000102030405', $port->getRawOriginal('ifPhysAddress'));
        $this->assertSame('transit', $port->port_descr_type);
        $this->travel(300)->seconds();
        $module->poll($os, $store);
        $this->assertEquals(0, $port->fresh()->ifInOctets_rate);
        $this->travel(300)->seconds();
        $module->poll($os, $store);
        $this->assertEquals(0, $port->fresh()->ifInOctets_delta);
        $this->assertSame(['0', '900', '900', '0'], $values);
        $this->assertNull($other->fresh()->poll_time);
    }

    public function testUnchangedPortMetadataRequiresOnlyTwoCounterWrites(): void
    {
        LibrenmsConfig::set('modules.ports.version', 2);
        $device = Device::factory()->create(['os' => 'generic']);
        $os = $this->os($device);
        $rows = [];
        foreach (range(1, 20) as $index) {
            $rows[$index] = ['ifName' => "eth$index", 'ifDescr' => "Ethernet $index", 'ifOperStatus' => 'up', 'ifInOctets' => $index];
        }
        new PortInventory($rows)->sync($device);
        $os->expects('pollPorts')->twice()->andReturn($rows + [99 => ['ifDescr' => 'undiscovered']]);
        $module = new Ports;
        $store = Mockery::mock(DataStorageInterface::class)->shouldIgnoreMissing();
        $module->poll($os, $store);
        $this->travel(300)->seconds();

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        $module->poll($os, $store);
        $queries = array_column(DB::connection()->getQueryLog(), 'query');
        DB::connection()->disableQueryLog();
        $writes = array_filter($queries, fn ($sql) => preg_match('/^(insert|update|delete)/i', $sql));
        $this->assertCount(2, $writes, implode("\n", $writes));
        foreach (['ports', 'ports_statistics'] as $table) {
            $reads = array_filter($queries, fn ($sql) => preg_match('/^select .* from ["`]' . $table . '["`]/i', $sql));
            $this->assertCount(1, $reads, implode("\n", $reads));
        }
        $this->assertDatabaseCount('ports_statistics', 20);
        $this->assertDatabaseCount('ports', 20);
    }

    public function testRegularAndSelectedPollingReadTheSameRecordedCounters(): void
    {
        $this->requireSnmpsim();
        $this->freezeTime();
        LibrenmsConfig::set('modules.ports.version', 2);
        $device = Device::factory()->create(['hostname' => $this->getSnmpsimIp(), 'os' => 'ios']);
        DeviceCache::setPrimary($device->device_id);
        $device = DeviceCache::refresh($device->device_id);
        $method = new DevicePollingMethod([
            'method_type' => PollingMethodType::Snmp, 'enabled' => true,
            'settings' => ['port' => $this->getSnmpsimPort(), 'max_repeaters' => 30],
        ]);
        $method->setRelation('secret', new Secret(['secret_type' => SecretType::SnmpCommunity, 'data' => ['community' => 'ios']]));
        $device->setRelation('pollingMethods', collect([$method]));
        $attributes = $device->getAttributes();
        $os = OS::make($attributes);
        $module = new Ports;
        $store = Mockery::mock(DataStorageInterface::class)->shouldIgnoreMissing();
        $walked = [];
        \Event::listen(\App\Events\SnmpQueryExecuted::class, function ($event) use (&$walked): void {
            if ($event->method === 'snmpwalk') {
                array_push($walked, ...$event->oids);
            }
        });
        $module->discover($os);
        $this->assertNotContains('IF-MIB::ifAdminStatus', $walked);
        $this->assertNotContains('IF-MIB::ifHCInOctets', $walked);
        $this->assertContains('ifHCInOctets', explode(',', $device->getAttrib('ports_if_mib_columns')));
        $walked = [];
        $module->poll($os, $store);
        $this->assertContains('IF-MIB::ifHCInOctets', $walked);
        foreach (['ifXTable', 'ifName', 'ifDescr', 'ifAlias', 'ifConnectorPresent'] as $field) {
            $this->assertNotContains("IF-MIB::$field", $walked);
        }
        $this->travel(300)->seconds();
        $device->ports()->where('ifIndex', '!=', 10115)->update(['disabled' => true]);
        $port = $device->ports()->isValid()->sole();
        $module->poll($os, $store);
        $port->refresh();
        $this->assertEquals(55615, $port->ifInErrors);
        $this->assertGreaterThan(0, $port->ifInOctets);

        $this->travel(300)->seconds();
        $device->setAttrib('selected_ports', 'true');
        $module->poll($os, $store);
        $this->assertEquals($port->ifInOctets, $port->fresh()->ifInOctets);
        $this->assertEquals(55615, $port->fresh()->ifInErrors);
        $this->assertEquals(300, $port->fresh()->poll_period);
    }

    public function testSparseHighCapacityColumnUsesThe32BitCounterForMissingInterfaces(): void
    {
        LibrenmsConfig::set('enable_ports_etherlike', false);
        $device = Device::factory()->create(['os' => 'generic']);
        $device->setRelation('pollingMethods', collect([new DevicePollingMethod([
            'method_type' => PollingMethodType::Snmp, 'enabled' => true, 'settings' => ['port_association_mode' => 'ifIndex'],
        ])]));
        $device->setAttrib('ports_if_mib_columns', 'ifOperStatus,ifInOctets,ifHCInOctets');
        foreach ([1, 2] as $index) {
            $device->ports()->create(['ifIndex' => $index, 'ifName' => "eth$index"]);
        }
        $os = Mockery::mock(OS::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $os->allows('getDevice')->andReturn($device);
        $os->allows('getName')->andReturn('generic');
        $os->expects('portTable')->with(['IF-MIB::ifOperStatus', 'IF-MIB::ifHCInOctets', 'EtherLike-MIB::dot3StatsDuplexStatus'], true)
            ->andReturn([1 => ['ifOperStatus' => 'up', 'ifHCInOctets' => '0'], 2 => ['ifOperStatus' => 'up']]);
        $os->expects('portTable')->with(['IF-MIB::ifInOctets'], true)->andReturn([1 => ['ifInOctets' => '10'], 2 => ['ifInOctets' => '42']]);
        $ports = $device->ports->keyBy('ifIndex')->all();
        $rows = $os->pollPorts($ports, false);
        $this->assertSame('0', $rows[1]['ifHCInOctets']);
        $this->assertSame('42', $rows[2]['ifInOctets']);
        $this->assertArrayNotHasKey('ifHCInOctets', $rows[2]);
    }

    public function testCustomDescriptionParsersRunDuringDiscovery(): void
    {
        $root = sys_get_temp_dir() . '/port-parser-' . bin2hex(random_bytes(8));
        File::makeDirectory("$root/includes", recursive: true);
        LibrenmsConfig::set('install_dir', $root);
        LibrenmsConfig::set('port_descr_parser', 'includes/custom.php');
        $device = Device::factory()->create();
        $inventory = new PortInventory([1 => ['ifDescr' => 'eth1', 'ifAlias' => 'uplink']]);
        try {
            file_put_contents("$root/includes/custom.php", '<?php $port_ifAlias = ["descr" => $this_port["ifAlias"], "circuit" => $port_id];');
            $inventory->sync($device);
            $port = $device->ports()->sole();
            $this->assertSame('uplink', $port->port_descr_descr);
            $this->assertEquals($port->port_id, $port->port_descr_circuit);
            file_put_contents("$root/includes/custom.php", '<?php return fn (string $ifName, int $port_id) => ["descr" => $ifName . "/" . $port_id];');
            $inventory->sync($device);
            $this->assertSame('eth1/' . $port->port_id, $port->fresh()->port_descr_descr);
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function testVendorOnlyPortsUseTheSameIdentityInDiscoveryAndPolling(): void
    {
        $device = Device::factory()->create(['os' => 'bison']);
        $device->setRelation('pollingMethods', collect([new DevicePollingMethod([
            'method_type' => PollingMethodType::Snmp, 'enabled' => true, 'settings' => ['port_association_mode' => 'ifName'],
        ])]));
        $os = Mockery::mock(Bison::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $os->allows('getDevice')->andReturn($device);
        $rows = [
            1 => ['vifPort' => 'wan', 'vifName' => 'WAN uplink', 'vifRxOctets' => 42],
            2 => ['vifPort' => 'lan', 'vifName' => 'LAN interface', 'vifRxOctets' => 15],
        ];
        $os->expects('portTable')->with(['BISON-ROUTER-MIB::vifCvid', 'BISON-ROUTER-MIB::vifName', 'BISON-ROUTER-MIB::vifPort'])->andReturn($rows);
        $os->expects('portTable')->with(['BISON-ROUTER-MIB::vifRxOctets', 'BISON-ROUTER-MIB::vifTxOctets', 'BISON-ROUTER-MIB::vifRxPkts',
            'BISON-ROUTER-MIB::vifTxPkts', 'BISON-ROUTER-MIB::vifName', 'BISON-ROUTER-MIB::vifPort'])->twice()->andReturn($rows, [1 => ['vifPort' => 'replacement']] + $rows);
        $os->discoverPorts()->sync($device);
        $this->assertCount(2, $device->ports);
        $known = $device->ports->where('ifName', 'wan')->keyBy('ifIndex')->all();
        $sample = $os->pollPorts($known, false);
        $this->assertSame([1001], array_keys($sample));
        $this->assertSame(42, $sample[1001]['ifInOctets']);
        $this->assertSame([], $os->pollPorts($known, true));
    }

    public function testAlternativeMethodCanSupplyTheInventoryAndCounters(): void
    {
        LibrenmsConfig::set('modules.ports.version', 2);
        $registry = Mockery::mock(PollingMethodRegistry::class)->makePartial();
        $registry->allows('providing')->with(PortDataSource::class)->andReturn([PollingMethodType::UnixAgent]);
        $this->app->instance(PollingMethodRegistry::class, $registry);
        $device = Device::factory()->create();
        $source = Mockery::mock(PortDataSource::class);
        $os = $this->os($device, PollingMethodType::UnixAgent, $source);
        $source->expects('discoverPorts')->andReturn(new PortInventory([5 => ['ifDescr' => 'eth5']]));
        $source->expects('pollPorts')->andReturn([5 => ['ifInOctets' => 45]]);
        $module = new Ports;
        $module->discover($os);
        $module->poll($os, Mockery::mock(DataStorageInterface::class)->shouldIgnoreMissing());
        $this->assertEquals(45, $device->ports()->sole()->ifInOctets);
    }

    private function os(Device $device, PollingMethodType $method = PollingMethodType::Snmp, ?PortDataSource $source = null): OS&Mockery\MockInterface
    {
        $device->setRelation('pollingMethods', collect([new DevicePollingMethod(['method_type' => $method, 'enabled' => true])]));
        $os = Mockery::mock(OS::class);
        $os->allows('getDevice')->andReturn($device);
        $os->allows('dataSource')->with($method, PortDataSource::class)->andReturn($source ?? $os);

        return $os;
    }
}

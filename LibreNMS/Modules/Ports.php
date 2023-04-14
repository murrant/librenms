<?php
/**
 * Ports.php
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
 * @copyright  2023 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Modules;

use App\Facades\Rrd;
use App\Models\Device;
use App\Models\ModuleConfig;
use App\Models\Port;
use App\Models\PortStatistic;
use LibreNMS\Config;
use LibreNMS\Data\Source\SnmpResponse;
use LibreNMS\DB\SyncsModels;
use LibreNMS\Enum\PortAssociationMode;
use LibreNMS\Enum\PortDisable;
use LibreNMS\OS;
use LibreNMS\RRD\RrdDefinition;
use LibreNMS\Util\Number;
use LibreNMS\Util\StringHelpers;
use Log;
use SnmpQuery;
use Symfony\Component\Console\Helper\Table;

class Ports extends LegacyModule implements \LibreNMS\Interfaces\Module
{
    use SyncsModels;

    protected int $version;

    protected array $field_alias = [
        'ifDescr' => 'IF-MIB::ifDescr',
        'ifType' => 'IF-MIB::ifType',
        'ifMtu' => 'IF-MIB::ifMtu',
        'ifSpeed' => 'IF-MIB::ifSpeed',
        'ifPhysAddress' => 'IF-MIB::ifPhysAddress',
        'ifOperStatus' => 'IF-MIB::ifOperStatus',
        'ifAdminStatus' => 'IF-MIB::ifAdminStatus',
        'ifLastChange' => 'IF-MIB::ifLastChange',
        'ifDuplex' => 'IF-MIB::ifDuplex',
        'ifTrunk' => 'IF-MIB::ifTrunk',
        'ifVlan' => 'IF-MIB::ifVlan',
        'ifInOctets' => 'IF-MIB::ifInOctets',
        'ifInUcastPkts' => 'IF-MIB::ifInUcastPkts',
        'ifInNUcastPkts' => 'IF-MIB::ifInNUcastPkts', // deprecated
        'ifInDiscards' => 'IF-MIB::ifInDiscards',
        'ifInErrors' => 'IF-MIB::ifInErrors',
        'ifInUnknownProtos' => 'IF-MIB::ifInUnknownProtos',
        'ifOutOctets' => 'IF-MIB::ifOutOctets',
        'ifOutUcastPkts' => 'IF-MIB::ifOutUcastPkts',
        'ifOutNUcastPkts' => 'IF-MIB::ifOutNUcastPkts', // deprecated
        'ifOutDiscards' => 'IF-MIB::ifOutDiscards',
        'ifOutErrors' => 'IF-MIB::ifOutErrors',
        // part of ifXTable, may not exist
        'ifName' => 'IF-MIB::ifName',
        'ifAlias' => 'IF-MIB::ifName',
        'ifConnectorPresent' => 'IF-MIB::ifConnectorPresent',
        'ifInMulticastPkts' => 'IF-MIB::ifInMulticastPkts',
        'ifOutMulticastPkts' => 'IF-MIB::ifOutMulticastPkts',
        'ifInBroadcastPkts' => 'IF-MIB::ifInBroadcastPkts',
        'ifOutBroadcastPkts' => 'IF-MIB::ifOutBroadcastPkts',
        // 'ifOutQLen' // deprecated
    ];

    protected array $hc_alias = [
        'ifInOctets' => 'IF-MIB::ifHCInOctets',
        'ifOutOctets' => 'IF-MIB::ifHCOutOctets',
        'ifInUcastPkts' => 'IF-MIB::ifHCInUcastPkts',
        'ifOutUcastPkts' => 'IF-MIB::ifHCOutUcastPkts',
        'ifInBroadcastPkts' => 'IF-MIB::ifHCInBroadcastPkts',
        'ifOutBroadcastPkts' => 'IF-MIB::ifHCOutBroadcastPkts',
        'ifInMulticastPkts' => 'IF-MIB::ifHCInMulticastPkts',
        'ifOutMulticastPkts' => 'IF-MIB::ifHCOutMulticastPkts',
    ];

    protected array $discovery_base_fields = [
        'ifName',
        'ifAlias',
        'ifDescr',
        'ifSpeed',
        'ifOperStatus',
        'ifAdminStatus',
        'ifConnectorPresent',
        // other
        'ifType',
        'ifLastChange',
        'ifMtu',
        'ifDuplex',
        'ifTrunk',
        'ifVlan',
        'ifPhysAddress',
    ];

    protected array $poller_base_fields = [
        'ifSpeed',
        'ifOperStatus',
        'ifAdminStatus',
    ];

    public const DS_FIELD_MAP = [
        'INOCTETS' => 'ifInOctets',
        'OUTOCTETS' => 'ifOutOctets',
        'INERRORS' => 'ifInErrors',
        'OUTERRORS' => 'ifOutErrors',
        'INUCASTPKTS' => 'ifInUcastPkts',
        'OUTUCASTPKTS' => 'ifOutUcastPkts',
        'INNUCASTPKTS' => 'ifInNUcastPkts',
        'OUTNUCASTPKTS' => 'ifOutNUcastPkts',
        'INDISCARDS' => 'ifInDiscards',
        'OUTDISCARDS' => 'ifOutDiscards',
        'INUNKNOWNPROTOS' => 'ifInUnknownProtos',
        'INBROADCASTPKTS' => 'ifInBroadcastPkts',
        'OUTBROADCASTPKTS' => 'ifOutBroadcastPkts',
        'INMULTICASTPKTS' => 'ifInMulticastPkts',
        'OUTMULTICASTPKTS' => 'ifOutMulticastPkts',
    ];

    public const BASE_PORT_STATS_FIELDS = [
        'ifInErrors',
        'ifOutErrors',
        'ifInUcastPkts',
        'ifOutUcastPkts',
        'ifInOctets',
        'ifOutOctets',
    ];

    public const PORT_STATS_FIELDS = [
        'ifInNUcastPkts',
        'ifOutNUcastPkts',
        'ifInDiscards',
        'ifOutDiscards',
        'ifInUnknownProtos',
        'ifInBroadcastPkts',
        'ifOutBroadcastPkts',
        'ifInMulticastPkts',
        'ifOutMulticastPkts',
    ];

    public const PORT_PREV_FIELDS = [
        'ifOperStatus',
        'ifAdminStatus',
        'ifSpeed',
    ];

    public function __construct()
    {
        $this->version = Config::get('modules.ports.version', 1);
        parent::__construct('ports');
    }

    /**
     * @inheritDoc
     */
    public function discover(OS $os): void
    {
        if ($this->version != 2) {
            parent::discover($os);
            return;
        }
        $device = $os->getDevice();

        $this->field_alias = $this->getFieldAlias($this->field_alias);

        $module_config = ModuleConfig::firstOrCreate([
            'device_id' => $device->device_id,
            'module' => 'ports',
        ]);
        $module_config->config = [
            'field_alias' => $this->field_alias,
            'per_port_polling' => Config::getOsSetting($os->getName(), 'polling.selected_ports') || $device->getAttrib('selected_ports') == 'true',
            'etherlike' => $this->field_alias['ifDuplex'] == 'EtherLike-MIB::dot3StatsDuplexStatus'
        ];
        $module_config->save();

        $base_oids = array_intersect_key($this->field_alias, array_flip($this->discovery_base_fields));
        $base_data = SnmpQuery::enumStrings()->walk($base_oids);


        $ports = $base_data->mapTable(function ($data, $ifIndex) use ($device) {
            $port = new \App\Models\Port;
            $port->ifIndex = $ifIndex;
            $port->device_id = $device->device_id;
            $this->fillPortFields($port, $data, $this->discovery_base_fields);
            $port->disabled = $this->shouldDisablePort($port)->value;

            return $port;
        });


        $this->syncModels($device, 'ports', $ports); // TODO soft delete
    }

    /**
     * @inheritDoc
     */
    public function poll(OS $os): void
    {
        if ($this->version != 2) {
            parent::poll($os);
            return;
        }
        $device = $os->getDevice();
        $device->load('ports', 'ports.statistics'); // eager load all ports and statistics

        // load the config TODO move to the poller code.
        $config = ModuleConfig::firstWhere(['module' => 'ports', 'device_id' => $device->device_id])->config;
        $port_assoc_mode = PortAssociationMode::getName($device->port_association_mode);
        $fields = array_merge($this->poller_base_fields, self::BASE_PORT_STATS_FIELDS, self::PORT_STATS_FIELDS, [$port_assoc_mode]); // collect all expected fields
        $this->field_alias = $config['field_alias'];

        // collect snmp data from the device and save the time it was collected
        $data = $config['per_port_polling'] ? $this->pollPortsWithGets($device, $fields) : $this->pollPortsWithWalks($fields);
        $poll_time = time();

        // filter the stats fields they go in the ports_stats table
        $port_model_fields = array_diff($fields, self::PORT_STATS_FIELDS);

        // fill the data into port objects
        $new_ports = $data->mapTable(function ($data, $ifIndex) use ($port_model_fields, $device, $poll_time) {
            $port = new \App\Models\Port;
            $port->ifIndex = $ifIndex;
            $port->device_id = $device->device_id;
            $port->poll_time = $poll_time;
            // TODO handle ifAdminStatus and ifOperStatus skipping
            $this->fillPortFields($port, $data, $port_model_fields);
            $this->runPortDescrParser($port);

            return $port;
        });

        $ports = $this->syncModels($device, 'ports', $new_ports); // TODO soft delete

        // update the ports_statistics and others
        $statistics_data = $data->table(1);
        $ports->each(function (Port $port) use ($statistics_data, $os, $poll_time) {
            if ($port->disabled) {
                return; // skip disabled ports
            }
            $this->savePortStatistics($port, $statistics_data[$port->ifIndex]);
            $this->updatePortRrd($port, $statistics_data[$port->ifIndex], $os);
            $this->updatePortPoe($port, $statistics_data[$port->ifIndex], $os);
        });

        $this->printPorts($ports);
    }

    protected function pollPortsWithWalks(array $fields): SnmpResponse
    {
        $oids = array_intersect_key($this->field_alias, array_flip($fields));

        return SnmpQuery::enumStrings()->walk($oids);
    }

    protected function pollPortsWithGets(Device $device, array $fields): SnmpResponse
    {
        $indexes = $device->ports->where('disabled', 0)->pluck('ifIndex');

//        unset($fields['ifAdminStatus'], $fields['ifOperStatus']); // don't fetch twice
//        $port_status = SnmpQuery::enumStrings()->walk(['IF-MIB::ifAdminStatus', 'IF-MIB::ifOperStatus']); // TODO use get in some cases?

        // TODO check if*Status and previous value

        $oids = [];
        foreach ($indexes as $ifIndex) {
            foreach ($fields as $field) {
                $oids[] = $this->field_alias[$field] . '.' . $ifIndex;
            }
        }

        if (empty($oids)) {
            return new SnmpResponse('');
        }

        return SnmpQuery::enumStrings()->get($oids);
    }

    // TODO create interface and move to OS
    protected function getFieldAlias(array $defaults): array
    {
        // tests
        $mib_tests = SnmpQuery::next([
            'IF-MIB::ifAlias',
            'IF-MIB::ifHCOutOctets',
            'IF-MIB::ifHighSpeed',
            'EtherLike-MIB::dot3StatsDuplexStatus',
            'Q-BRIDGE-MIB::dot1qPvid',
        ]);

        foreach ($mib_tests->values() as $oid => $value) {
            preg_match('/^.+::[a-zA-Z0-9]+/', $oid, $oid_matches);
            $field = match ($oid_matches[0]) {
                'IF-MIB::ifAlias' => 'ifAlias',
                'IF-MIB::ifHighSpeed' => 'ifSpeed',
                'IF-MIB::ifHCOutOctets' => 'ifOutOctets',
                'Q-BRIDGE-MIB::dot1qPvid' => 'ifVlan',
                'EtherLike-MIB::dot3StatsDuplexStatus' => 'ifDuplex',
                default => false,
            };

            // if not a matched field and the value is >= 0 (if numeric)
            if ($field && (!is_numeric($value) || $value >= 0)) {
                $defaults[$field] = $oid_matches[0];
            }
        }

        if ($defaults['ifOutOctets'] == 'IF-MIB::ifHCOutOctets') {
            \Log::debug('HC OIDs enabled!');
            $defaults = array_replace($defaults, $this->hc_alias);
        }

        return $defaults;
    }

    private function fillPortFields(Port $port, array $data, array $fields): Port
    {
        foreach ($fields as $field) {
            $port->setAttribute($field, $data[$this->field_alias[$field]]);
        }

        // data tweaks
        if ($this->field_alias['ifSpeed'] == 'IF-MIB::ifHighSpeed') {
            $port->ifSpeed = $port->ifSpeed * 1000;
        }

        if (str_contains($port->ifPhysAddress, ':')) {
            $mac_split = explode(':', $port->ifPhysAddress);
            $port->ifPhysAddress = zeropad($mac_split[0]) . zeropad($mac_split[1]) . zeropad($mac_split[2]) . zeropad($mac_split[3]) . zeropad($mac_split[4] ?? '') . zeropad($mac_split[5] ?? '');
        }

        if (! $port->device->getAttrib('ifName:' . $port->ifName)) {
            $port->getOriginal('ifAlias');
        } else {
            $port->ifAlias = StringHelpers::inferEncoding($port->ifAlias);
        }

        if (! $port->device->getAttrib('ifSpeed:' . $port->ifName)) {
            $port->ifSpeed = $port->getOriginal('ifSpeed');
        }

        return $port;
    }

    private function shouldDisablePort(Port $port): PortDisable
    {
        // check empty values first
        if (empty($port->ifDescr)) {
            // If these are all empty, we are just going to show blank names in the ui
            if (empty($port->ifAlias) && empty($port->ifName)) {
                Log::debug("ignored: empty ifDescr, ifAlias and ifName\n");

                return PortDisable::empty;
            }

            // ifDescr should not be empty unless it is explicitly allowed
            if (! Config::getOsSetting($port->device->os, 'empty_ifdescr')) {
                Log::debug("ignored: empty ifDescr\n");

                return PortDisable::empty_ifdescr;
            }
        }

        foreach (Config::getOsSetting($port->device->os, 'bad_ifdescr_regexp') as $bir) {
            if (preg_match($bir . 'i', $port->ifDescr)) {
                Log::debug("ignored by ifDescr: $port->ifDescr (matched: $bir)\n");

                return PortDisable::bad_ifdescr_regexp;
            }
        }

        foreach (Config::getOsSetting($port->device->os, 'bad_ifname_regexp') as $bnr) {
            if (preg_match($bnr . 'i', $port->ifName)) {
                Log::debug("ignored by ifName: $port->ifName (matched: $bnr)\n");

                return PortDisable::bad_ifname_regexp;
            }
        }

        foreach (Config::getOsSetting($port->device->os, 'bad_ifalias_regexp') as $bar) {
            if (preg_match($bar . 'i', $port->ifAlias)) {
                Log::debug("ignored by ifName: $port->ifAlias (matched: $bar)\n");

                return PortDisable::bad_ifalias_regexp;
            }
        }

        foreach (Config::getOsSetting($port->device->os, 'bad_iftype_regexp') as $bt) {
            if (preg_match($bt . 'i', $port->ifType)) {
                Log::debug("ignored by ifType: $port->ifType (matched: $bt )\n");

                return PortDisable::bad_iftype_regexp;
            }
        }

        return PortDisable::enable;
    }

    private function shouldRrdTune(Port $port): bool
    {
        $port_tune = $port->device->getAttrib('ifName_tune:' . $port->ifName);
        $device_tune = $port->device->getAttrib('override_rrdtool_tune');

        return $port_tune == 'true' ||
        ($device_tune == 'true' && $port_tune != 'false') ||
        (Config::get('rrdtool_tune') && $port_tune != 'false' && $device_tune != 'false');
    }

    private function rrdName(Port $port, ?string $suffix = null)
    {
        $parts = ['port', "id$port->port_id",];
        if ($suffix) {
            $parts[] = $suffix;
        }

        return Rrd::name($port->device->hostname, $parts);
    }

    private function runPortDescrParser(Port $port)
    {
        // TODO
    }

    private function savePortStatistics(Port $port, array $port_statistics): void
    {
// if statistics entry doesn't exist, create a new one
        if ($port->statistics === null) {
            $port_stats = new PortStatistic(['port_id' => $port->port_id]);
            $port_stats->setRelation('port', $port); // prevent extra sql query
            $port->setRelation('statistics', $port_stats);
        }

        // fill the new data
        foreach (self::PORT_STATS_FIELDS as $field) {
            $port->statistics->$field = $port_statistics[$this->field_alias[$field]];
        }

        $port->statistics->save();
    }


    private function updatePortRrd(Port $port, array $port_stats, OS $os): void
    {
        $rrd_name = $this->rrdName($port);
        $rrd_max = 12500000000;
        if ($this->shouldRrdTune($port)) {
            Rrd::tune('port', $rrd_name, $port->ifSpeed);
            $rrd_max = max($port->ifSpeed, $rrd_max);
        }
        $rrd_def = RrdDefinition::make();
        foreach (self::DS_FIELD_MAP as $ds => $field) {
            $rrd_def->addDataset($ds, 'DERIVE', 0, $rrd_max);
        }
        $fields = array_map(function ($field) use ($port, $port_stats) {
            return $port_stats[$this->field_alias[$field]] ?? null;
        }, self::DS_FIELD_MAP);
        $tags = $port->only(['ifName', 'ifDescr', 'ifIndex']);
        $tags['rrd_name'] = $rrd_name;
        $tags['rrd_def'] = $rrd_def;
        app('Datastore')->put($os->getDeviceArray(), 'ports', $tags, $fields);
    }

    private function updatePortPoe(Port $port, array $port_stats, OS $os): void
    {
        // TODO actually supply data
        $poe_alias = array_intersect_key($this->field_alias, ['PortPwrAllocated' => 1, 'PortPwrAvailable' => 1, 'PortConsumption' => 1, 'PortMaxPwrDrawn' => 1]);
        if (empty($poe_alias)) {
            return;
        }

        $rrd_def = RrdDefinition::make();
        $fields = [];
        foreach ($poe_alias as $field => $oid) {
            $rrd_def->addDataset($field, 'GAUGE', 0);
            $fields[$field] = $port_stats[$oid];
        }

        app('Datastore')->put($os->getDeviceArray(), 'poe', [
            'ifName' => $port->ifName,
            'rrd_name' => $this->rrdName($port, 'poe'),
            'rrd_def' => $rrd_def,
        ], $fields);
    }

    /**
     * @param \Illuminate\Support\Collection $ports
     * @return void
     */
    private function printPorts(\Illuminate\Support\Collection $ports): void
    {
        $out = new \Symfony\Component\Console\Output\ConsoleOutput();
        $table = new Table($out);
        $table->setHeaders(['Port', 'VLAN', 'Speed', 'MTU', 'Bits In', 'Bits Out']);
        foreach ($ports as $port) {
            dump($port->ifSpeed);
            /** @var Port $port */
            $table->addRow([
                $port->getShortLabel(),
                $port->ifVlan,
                Number::formatSi($port->ifSpeed, 2, 3, 'bps'),
                $port->ifMtu,
                Number::formatSi($port->ifInOctets_rate * 8, 2, 3, 'bps'),
                Number::formatSi($port->ifOutOctets_rate * 8, 2, 3, 'bps'),
            ]);
        }
        $table->render();
    }
}

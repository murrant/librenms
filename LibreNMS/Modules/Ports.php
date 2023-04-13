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

use App\Models\Device;
use App\Models\ModuleConfig;
use App\Models\Port;
use App\Models\PortStatistic;
use LibreNMS\Config;
use LibreNMS\Data\Source\SnmpResponse;
use LibreNMS\DB\SyncsModels;
use LibreNMS\Enum\PortAssociationMode;
use LibreNMS\OS;
use SnmpQuery;

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
        'ifPromiscuousMode' => 'IF-MIB::ifPromiscuousMode',
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
        'ifPromiscuousMode',
    ];

    protected array $poller_base_fields = [
        'ifSpeed',
        'ifOperStatus',
        'ifAdminStatus',
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
        $device_id = $os->getDeviceId();

        $this->field_alias = $this->getFieldAlias($this->field_alias);

        $module_config = ModuleConfig::firstOrCreate([
            'device_id' => $os->getDeviceId(),
            'module' => 'ports',
        ]);
        $module_config->config = [
            'field_alias' => $this->field_alias,
            'per_port_polling' => Config::getOsSetting($os->getName(), 'polling.selected_ports') || $os->getDevice()->getAttrib('selected_ports') == 'true',
        ];
        $module_config->save();

        $base_oids = array_intersect_key($this->field_alias, array_flip($this->discovery_base_fields));
        $base_data = SnmpQuery::enumStrings()->walk($base_oids);


        $ports = $base_data->mapTable(function ($data, $ifIndex) use ($device_id) {
            $port = new \App\Models\Port;
            $port->ifIndex = $ifIndex;
            $port->device_id = $device_id;

            return $this->fillPortFields($port, $data, $this->discovery_base_fields);
        });


        $this->syncModels($os->getDevice(), 'ports', $ports); // TODO soft delete
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
        $device_id = $os->getDeviceId();
        $os->getDevice()->load('ports', 'ports.statistics'); // eager load all ports and statistics

        // load the config TODO move to the poller code.
        $config = ModuleConfig::firstWhere(['module' => 'ports', 'device_id' => $os->getDeviceId()])->config;
        $port_assoc_mode = PortAssociationMode::getName($os->getDevice()->port_association_mode);
        $fields = array_merge($this->poller_base_fields, self::BASE_PORT_STATS_FIELDS, self::PORT_STATS_FIELDS, [$port_assoc_mode]); // collect all expected fields
        $this->field_alias = $config['field_alias'];

        // collect snmp data from the device and save the time it was collected
        $data = $config['per_port_polling'] ? $this->pollPortsWithGets($os->getDevice(), $fields) : $this->pollPortsWithWalks($fields);
        $poll_time = time();

        // filter the stats fields they go in the ports_stats table
        $port_model_fields = array_diff($fields, self::PORT_STATS_FIELDS);

        // fill the data into port objects
        $new_ports = $data->mapTable(function ($data, $ifIndex) use ($port_model_fields, $device_id, $poll_time) {
            $port = new \App\Models\Port;
            $port->ifIndex = $ifIndex;
            $port->device_id = $device_id;
            $port->poll_time = $poll_time;

            return $this->fillPortFields($port, $data, $port_model_fields);
        });

        $ports = $this->syncModels($os->getDevice(), 'ports', $new_ports); // TODO soft delete

        // update the ports_statistics table too
        $statistics_data = $data->table(1);
        $ports->each(function (Port $port) use ($statistics_data, $poll_time) {
            // if statistics entry doesn't exist, create a new one
            if ($port->statistics === null) {
                $port_stats = new PortStatistic(['port_id' => $port->port_id]);
                $port_stats->setRelation('port', $port); // prevent extra sql query
                $port->setRelation('statistics', $port_stats);
            }

            // fill the new data
            foreach (self::PORT_STATS_FIELDS as $field) {
                $port->statistics->$field = $statistics_data[$port->ifIndex][$this->field_alias[$field]];
            }

            $port->statistics->save();
        });
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
    protected function getFieldAlias(array $defaults)
    {
        // tests
        $mib_tests = SnmpQuery::next([
            'IF-MIB::ifAlias',
            'IF-MIB::ifHCOutOctets',
            'IF-MIB::ifHighSpeed',
            'EtherLike-MIB::dot3StatsIndex',
            'EtherLike-MIB::dot3StatsDuplexStatus',
            'Q-BRIDGE-MIB::dot1qPvid',
        ]);

        foreach ($mib_tests->values() as $oid => $value) {
            preg_match('/^.+::[a-zA-Z0-9]+/', $oid, $oid_matches);
            $field = match($oid_matches[0]) {
                'IF-MIB::ifAlias' => 'ifAlias',
                'IF-MIB::ifHighSpeed' => 'ifSpeed',
                'IF-MIB::ifHCOutOctets' => 'ifOutOctets',
                'Q-BRIDGE-MIB::dot1qPvid' => 'ifVlan',
                default => false,
            };

            // if not a matched field and the value is >= 0 (if numeric)
            if ($field && (! is_numeric($value) || $value >= 0)) {
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
            $oid = $this->field_alias[$field];
            $value = $data[$oid];

            if ($field == 'ifSpeed' && $oid == 'IF-MIB::ifHighSpeed') {
                $value = $value * 1000;
            }

            $port->setAttribute($field, $value);
        }

        return $port;
    }
}

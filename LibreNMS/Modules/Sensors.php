<?php

/**
 * Sensors.php
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
 * @copyright  2025 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Modules;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use App\Models\Eventlog;
use App\Models\StateIndex;
use App\Observers\ModuleModelObserver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LibreNMS\DB\SyncsModels;
use LibreNMS\Device\YamlDiscovery;
use LibreNMS\Enum\Sensor;
use LibreNMS\Enum\Severity;
use LibreNMS\Interfaces\Data\DataStorageInterface;
use LibreNMS\OS;
use LibreNMS\Polling\ModuleStatus;
use LibreNMS\RRD\RrdDefinition;
use LibreNMS\Util\Number;
use LibreNMS\Util\StringHelpers;
use LibreNMS\Util\UserFuncHelper;
use SnmpQuery;

class Sensors implements \LibreNMS\Interfaces\Module
{
    use SyncsModels;

    /**
     * @inheritDoc
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function shouldDiscover(OS $os, ModuleStatus $status): bool
    {
        return $status->isEnabledAndDeviceUp($os->getDevice());
    }

    /**
     * @inheritDoc
     */
    public function shouldPoll(OS $os, ModuleStatus $status): bool
    {
        return $status->isEnabledAndDeviceUp($os->getDevice());
    }

    /**
     * @inheritDoc
     */
    public function discover(OS $os): void
    {
        $all_types = \LibreNMS\Enum\Sensor::values();
        $submodules = LibrenmsConfig::get('discovery_submodules.sensors', $all_types);
        $types = array_intersect($all_types, $submodules);

        $pre_cache = $os->preCache();
        $this->discoverLegacyPreSensors($os->getDevice());

        foreach ($types as $sensor_class) {
            $sensorType = \LibreNMS\Enum\Sensor::from($sensor_class);
            ModuleModelObserver::observe(\App\Models\Sensor::class, StringHelpers::niceCase($sensor_class));

            $sensors = new Collection;

            $discoveryInterface = $sensorType->discoveryInterface();
            if ($os instanceof $discoveryInterface) {
                Log::info("$sensor_class: ");
                $function = 'discover' . StringHelpers::toClass("$sensor_class sensors");
                $sensors = $os->$function();
            } else {
                $this->discoverLegacySensor(
                    device: $os->getDeviceArray(),
                    dir: LibrenmsConfig::get('install_dir') . '/includes/discovery/sensors/' . $sensor_class . '/',
                );
            }

            discovery_process($os, $sensor_class, $pre_cache);
            app('sensor-discovery')->sync(sensor_class: $sensor_class, poller_type: 'snmp');
//            $this->syncModelsByGroup($os->getDevice(), 'sensors', $sensors, ['sensor_class' => $type]);
            ModuleModelObserver::done();
        }
    }

    /**
     * @inheritDoc
     */
    public function poll(OS $os, DataStorageInterface $datastore): void
    {
        $all_sensors = $os->getDevice()->sensors()
            ->when(LibrenmsConfig::get('poller_submodules.sensors', []), fn ($q, $submodules) => $q->whereIn('sensor_class', $submodules))
//            ->where('poller_type', '!=', 'agent')
            ->get()
            ->groupBy('sensor_class');

        // fetch data for all non-custom polled sensors
        $oids = $all_sensors->filter(function ($sensor, $sensor_class) use ($os) {
            $polling_interface = Sensor::from($sensor_class)->getPollingInterface();

            return ! $os instanceof $polling_interface;
        })->flatten()->pluck('sensor_oid')->unique()->all();

        $snmp_data = empty($oids) ? [] : SnmpQuery::numeric()->get($oids)->values();

        foreach ($all_sensors as $sensor_class => $sensors) {
            $sensor_enum = Sensor::from($sensor_class);
            $polling_interface = $sensor_enum->getPollingInterface();
            foreach ($sensors as $sensor) {
                Log::info('Checking (' . $sensor->poller_type . ") $sensor_class " . $sensor->sensor_descr . '... ');

                if ($os instanceof $polling_interface) {
                    $polling_method = 'poll' . StringHelpers::toClass($sensor_class . ' sensors');
                    $sensor = $os->$polling_method($sensor);
                } elseif ($sensor->poller_type == 'snmp') {
                    $sensor_value = trim($snmp_data[$sensor->sensor_oid] ?? '');
                    $sensor_value = $this->pollLegacySensor(
                        device: $os->getDeviceArray(),
                        sensor_class: $sensor_class,
                        sensor_value: $sensor_value
                    );

                    if ($sensor_class == 'state' && ! is_numeric($sensor_value)) {
                        $state_value = DB::table('state_translations')
                            ->leftJoin('sensors_to_state_indexes', 'state_translations.state_index_id', '=', 'sensors_to_state_indexes.state_index_id')
                            ->where('sensors_to_state_indexes.sensor_id', $sensor->sensor_id)
                            ->where('state_translations.state_descr', 'LIKE', $sensor_value)
                            ->value('state_value');
                        d_echo('State value of ' . $sensor_value . ' is ' . $state_value . "\n");
                        if (is_numeric($state_value)) {
                            $sensor_value = $state_value;
                        }
                    }

                    $sensor_raw_value = $sensor_value;
                    $sensor_value = Number::extract($sensor_value);
                    if ($sensor->sensor_divisor && $sensor_value !== 0) {
                        $sensor_value /= $sensor->sensor_divisor;
                    }

                    if ($sensor->sensor_multiplier) {
                        $sensor_value *= $sensor->sensor_multiplier;
                    }

                    if (is_callable($sensor->user_func)) {
                        $user_func = $sensor->user_func;
                        $sensor_value = $user_func($sensor_value);
                    } elseif ($sensor->user_func) {
                        $sensor_value = (new UserFuncHelper($sensor_value, $sensor_raw_value, $sensor))->{$sensor->user_func}();
                    }

                    $sensor->sensor_prev = $sensor->sensor_current;
                    $sensor->sensor_current = $sensor_value;

                    $unit = $sensor_enum->unit();
                    Log::info("$sensor_value $unit");

                    // save to rrd
                    $fields = ['sensor' => $sensor_value];
                    $tags = [
                        'sensor_class' => $sensor->sensor_class,
                        'sensor_type' => $sensor->sensor_type,
                        'sensor_descr' => $sensor->sensor_descr,
                        'sensor_index' => $sensor->sensor_index,
                        'rrd_name' => ['sensor', $sensor->sensor_class, $sensor->sensor_type, $sensor->sensor_index],
                        'rrd_def' => RrdDefinition::make()->addDataset('sensor', $sensor->rrd_type),
                    ];
                    $datastore->put($os->getDeviceArray(), 'sensor', $tags, $fields);

                    // warn
                    $class = trans('sensors.' . $sensor->sensor_class . '.short');
                    if ($sensor->sensor_limit_low != '' && $sensor->sensor_prev > $sensor->sensor_limit_low && $sensor_value < $sensor->sensor_limit_low && $sensor->sensor_alert == 1) {
                        echo 'Alerting for ' . $os->getDevice()->hostname . ' ' . $sensor->sensor_descr . "\n";
                        Eventlog::log("$class under threshold: $sensor_value $unit (< {$sensor->sensor_limit_low} $unit)", $os->getDeviceId(), $sensor->sensor_class, Severity::Warning, $sensor->sensor_id);
                    } elseif ($sensor->sensor_limit != '' && $sensor->sensor_prev < $sensor->sensor_limit && $sensor_value > $sensor->sensor_limit && $sensor->sensor_alert == 1) {
                        echo 'Alerting for ' . $os->getDevice()->hostname . ' ' . $sensor->sensor_descr . "\n";
                        Eventlog::log("$class above threshold: $sensor_value $unit (> {$sensor->sensor_limit} $unit)", $os->getDeviceId(), $sensor->sensor_class, Severity::Warning, $sensor->sensor_id);
                    }
                    if ($sensor->sensor_class == 'state' && $sensor->sensor_prev != $sensor_value) {
                        $trans = DB::table('sensors_to_state_indexes')
                            ->leftJoin('state_translations', 'sensors_to_state_indexes.state_index_id', '=', 'state_translations.state_index_id')
                            ->where('sensors_to_state_indexes.sensor_id', $sensor->sensor_id)
                            ->whereIn('state_translations.state_value', [$sensor_value, $sensor->sensor_prev])
                            ->pluck('state_descr', 'state_value')
                            ->toArray();

                        Eventlog::log($class . ' sensor ' . ($sensor->sensor_descr ?? '') . ' has changed from ' . ($trans[$sensor->sensor_prev] ?? '#unamed state#') . "($sensor->sensor_prev) to " . ($trans[$sensor_value] ?? '#unamed state#') . " ($sensor_value)", $os->getDeviceId(), $class, Severity::Notice, $sensor->sensor_id);
                    }

                    $sensor->save();
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function dataExists(Device $device): bool
    {
        return $device->sensors()->exists();
    }

    /**
     * @inheritDoc
     */
    public function cleanup(Device $device): int
    {
        return $device->sensors()->delete();
    }

    /**
     * @inheritDoc
     */
    public function dump(Device $device, string $type): ?array
    {
        return [
            'sensors' => $device->sensors()
                ->leftJoin('sensors_to_state_indexes', 'sensors.sensor_id', '=', 'sensors_to_state_indexes.sensor_id')
                ->leftJoin('state_indexes', 'sensors_to_state_indexes.state_index_id', '=', 'state_indexes.state_index_id')
                ->orderBy('sensor_class')->orderBy('sensor_type')->orderBy('sensor_index')
                ->get()->map->makeHidden([
                    'device_id',
                    'sensor_id',
                    'state_translation_id',
                    'state_index_id',
                    'sensors_to_state_translations_id',
                    'lastupdate',
                ]),
            'state_indexes' => StateIndex::join('state_translations', 'state_indexes.state_index_id', '=', 'state_translations.state_index_id')
                ->joinSub(
                    DB::table('sensors_to_state_indexes as i')
                        ->leftJoin('sensors as s', 'i.sensor_id', '=', 's.sensor_id')
                        ->where('device_id', $device->device_id)
                        ->groupBy('i.state_index_id')
                        ->select('i.state_index_id'),
                    'd',
                    'd.state_index_id',
                    '=',
                    'state_indexes.state_index_id'
                )
                ->orderBy('state_indexes.state_name')
                ->orderBy('state_translations.state_value')
                ->select([
                    'state_indexes.state_name',
                    'state_translations.state_descr',
                    'state_translations.state_draw_graph',
                    'state_translations.state_value',
                    'state_translations.state_generic_value',
                ])
                ->get(),
        ];
    }

    private function discoverLegacySensor(array $device, string $dir): void
    {
        if (isset($device->os_group) && is_file($dir . $device->os_group . '.inc.php')) {
            include $dir . $device->os_group . '.inc.php';
        }
        if (is_file($dir . $device->os . '.inc.php')) {
            include $dir . $device->os . '.inc.php';
        }
        if (LibrenmsConfig::getOsSetting($device->os, 'rfc1628_compat', false)) {
            if (is_file($dir . '/rfc1628.inc.php')) {
                include $dir . '/rfc1628.inc.php';
            }
        }
    }

    private function pollLegacySensor(array $device, string $sensor_class, string $sensor_value): int|float|string|null
    {
        if (file_exists(LibrenmsConfig::get('install_dir') . '/includes/polling/sensors/' . $sensor_class . '/' . $device['os'] . '.inc.php')) {
            require LibrenmsConfig::get('install_dir') . '/includes/polling/sensors/' . $sensor_class . '/' . $device['os'] . '.inc.php';
        } elseif (isset($device['os_group']) && file_exists(LibrenmsConfig::get('install_dir') . '/includes/polling/sensors/' . $sensor_class . '/' . $device['os_group'] . '.inc.php')) {
            require LibrenmsConfig::get('install_dir') . '/includes/polling/sensors/' . $sensor_class . '/' . $device['os_group'] . '.inc.php';
        }

        return $sensor_value;
    }

    private function discoverLegacyPreSensors(Device $device): void
    {
        if ($device->os == 'rittal-cmc-iii-pu' || $device->os == 'rittal-lcp') {
            include 'includes/discovery/sensors/rittal-cmc-iii-sensors.inc.php';
        } else {
            // Run custom sensors
            require 'includes/discovery/sensors/cisco-entity-sensor.inc.php';
            require 'includes/discovery/sensors/entity-sensor.inc.php';
            require 'includes/discovery/sensors/ipmi.inc.php';
        }

        if ($device->os == 'netscaler') {
            include 'includes/discovery/sensors/netscaler.inc.php';
        }

        if ($device->os == 'openbsd') {
            include 'includes/discovery/sensors/openbsd.inc.php';
        }

        if ($device->os == 'linux') {
            include 'includes/discovery/sensors/rpigpiomonitor.inc.php';
        }

        if (isset($device->hardware) && str_contains($device->hardware, 'Dell')) {
            include 'includes/discovery/sensors/fanspeed/dell.inc.php';
            include 'includes/discovery/sensors/power/dell.inc.php';
            include 'includes/discovery/sensors/voltage/dell.inc.php';
            include 'includes/discovery/sensors/state/dell.inc.php';
            include 'includes/discovery/sensors/temperature/dell.inc.php';
        }

        if (isset($device->hardware) && str_contains($device->hardware, 'ProLiant')) {
            include 'includes/discovery/sensors/state/hp.inc.php';
        }

        if ($device->os == 'gw-eydfa') {
            include 'includes/discovery/sensors/gw-eydfa.inc.php';
        }
    }

    private function yamlDiscovery($os, $sensor_class, $pre_cache)
    {
        $discovery = $os->getDiscovery('sensors');
        $device = $os->getDeviceArray();

        if (! empty($discovery[$sensor_class]) && ! app('sensor-discovery')->canSkip(new \App\Models\Sensor(['sensor_class' => $sensor_class]))) {
            $sensor_options = [];
            if (isset($discovery[$sensor_class]['options'])) {
                $sensor_options = $discovery[$sensor_class]['options'];
            }

            Log::debug("Dynamic Discovery ($sensor_class): ");
            Log::debug($discovery[$sensor_class]);

            foreach ($discovery[$sensor_class]['data'] as $data) {
                $tmp_name = $data['oid'];

                if (! isset($pre_cache[$tmp_name])) {
                    continue;
                }

                $raw_data = (array) $pre_cache[$tmp_name];

                Log::debug("Data $tmp_name: ", $raw_data);
                $count = 0;

                foreach ($raw_data as $index => $snmp_data) {
                    $count++;
                    $user_function = null;
                    if (isset($data['user_func'])) {
                        $user_function = $data['user_func'];
                    }
                    // get the value for this sensor, check 'value' and 'oid', if state string, translate to a number
                    $data['value'] ??= $data['oid'];  // fallback to oid if value is not set

                    $snmp_value = $snmp_data[$data['value']] ?? '';
                    if (! is_numeric($snmp_value)) {
                        if ($sensor_class === 'temperature') {
                            // For temp sensors, try and detect fahrenheit values
                            if (is_string($snmp_value) && Str::endsWith($snmp_value, ['f', 'F'])) {
                                $user_function = 'fahrenheit_to_celsius';
                            }
                        }
                        preg_match('/-?\d*\.?\d+/', $snmp_value, $temp_response);
                        if (! empty($temp_response[0])) {
                            $snmp_value = $temp_response[0];
                        }
                    }

                    if (is_numeric($snmp_value)) {
                        $value = $snmp_value;
                    } elseif ($sensor_class === 'state') {
                        // translate string states to values (poller does this as well)
                        $states = array_column($data['states'], 'value', 'descr');
                        $value = $states[$snmp_value] ?? false;
                    } else {
                        $value = false;
                    }

                    $skippedFromYaml = YamlDiscovery::canSkipItem($value, $index, $data, $sensor_options, $pre_cache);

                    // Check if we have a "num_oid" value. If not, we'll try to compute it from textual OIDs with snmptranslate.
                    if (empty($data['num_oid'])) {
                        try {
                            $data['num_oid'] = YamlDiscovery::computeNumericalOID($os, $data);
                        } catch (\Exception) {
                            Log::debug('Error: We cannot find a numerical OID for ' . $data['value'] . '. Skipping this one...');
                            $skippedFromYaml = true;
                            // Because we don't have a num_oid, we have no way to add this sensor.
                        }
                    }

                    if ($skippedFromYaml === false && is_numeric($value)) {
                        Log::debug("Sensor fetched value: $value\n");

                        // process the oid (num_oid will contain index or str2num replacement calls)
                        $oid = trim(YamlDiscovery::replaceValues('num_oid', $index, null, $data, []));

                        // process the description
                        $descr = trim(YamlDiscovery::replaceValues('descr', $index, null, $data, $pre_cache));

                        // process the group
                        $group = trim(YamlDiscovery::replaceValues('group', $index, null, $data, $pre_cache)) ?: null;

                        // process the divisor - cannot be 0
                        if (isset($data['divisor'])) {
                            $divisor = (int) YamlDiscovery::replaceValues('divisor', $index, $count, $data, $pre_cache);
                        } elseif (isset($sensor_options['divisor'])) {
                            $divisor = (int) $sensor_options['divisor'];
                        } else {
                            $divisor = 1;
                        }
                        if ($divisor == 0) {
                            Log::warning('Divisor is not a nonzero number, defaulting to 1');
                            $divisor = 1;
                        }

                        // process the multiplier - zero is valid
                        if (isset($data['multiplier'])) {
                            $multiplier = YamlDiscovery::replaceValues('multiplier', $index, $count, $data, $pre_cache);
                        } elseif (isset($sensor_options['multiplier'])) {
                            $multiplier = $sensor_options['multiplier'];
                        } else {
                            $multiplier = 1;
                        }
                        if (is_numeric($multiplier)) {
                            $multiplier = (int) $multiplier;
                        } else {
                            Log::warning('Multiplier $multiplier is not a valid number, defaulting to 1');
                            $multiplier = 1;
                        }

                        // process the limits
                        // phpstan does not like $$var variables
                        $low_limit = $low_warn_limit = $warn_limit = $high_limit = null;

                        $limits = ['low_limit', 'low_warn_limit', 'warn_limit', 'high_limit'];
                        foreach ($limits as $limit) {
                            if (isset($data[$limit]) && is_numeric($data[$limit])) {
                                ${$limit} = $data[$limit];
                            } else {
                                ${$limit} = trim(YamlDiscovery::replaceValues($limit, $index, null, $data, $pre_cache));
                                if (is_numeric(${$limit})) {
                                    ${$limit} = (${$limit} / $divisor) * $multiplier;
                                }
                                if (is_numeric(${$limit}) && isset($user_function)) {
                                    if (is_callable($user_function)) {
                                        ${$limit} = $user_function(${$limit});
                                    } else {
                                        ${$limit} = (new UserFuncHelper(${$limit}))->{$user_function}();
                                    }
                                }
                            }
                        }

                        $sensor_name = $device->os;

                        if ($sensor_class === 'state') {
                            $sensor_name = $data['state_name'] ?? $data['oid'];
                            create_state_index($sensor_name, $data['states']);
                        } else {
                            // We default to 1 for both divisors / multipliers so it should be safe to do the calculation using both.
                            $value = ($value / $divisor) * $multiplier;
                        }

                        $entPhysicalIndex = YamlDiscovery::replaceValues('entPhysicalIndex', $index, null, $data,
                            $pre_cache) ?: null;
                        $entPhysicalIndex_measured = $data['entPhysicalIndex_measured'] ?? null;

                        //user_func must be applied after divisor/multiplier
                        if (isset($user_function)) {
                            if (is_callable($user_function)) {
                                $value = $user_function($value);
                            } else {
                                $value = (new UserFuncHelper($value, $snmp_data[$data['value']],
                                    $data))->{$user_function}();
                            }
                        }

                        $uindex = $index;
                        if (isset($data['index'])) {
                            if (str_contains($data['index'], '{{')) {
                                $uindex = trim(YamlDiscovery::replaceValues('index', $index, null, $data, $pre_cache));
                            } else {
                                $uindex = $data['index'];
                            }
                        }

                        app('sensor-discovery')->discover(new \App\Models\Sensor([
                            'poller_type' => $poller_type,
                            'sensor_class' => $sensor_class,
                            'device_id' => $device->device_id,
                            'sensor_oid' => $oid,
                            'sensor_index' => $uindex,
                            'sensor_type' => $sensor_name,
                            'sensor_descr' => $descr,
                            'sensor_divisor' => $divisor,
                            'sensor_multiplier' => $multiplier,
                            'sensor_limit' => $high_limit,
                            'sensor_limit_warn' => $warn_limit,
                            'sensor_limit_low' => $low_limit,
                            'sensor_limit_low_warn' => $low_warn_limit,
                            'sensor_current' => $value,
                            'entPhysicalIndex' => $entPhysicalIndex,
                            'entPhysicalIndex_measured' => $entPhysicalIndex_measured,
                            'user_func' => $user_function,
                            'group' => $group,
                            'rrd_type' => $data['rrd_type'] ?? 'GAUGE',
                        ]));
                    }
                }
            }
        }
    }
}

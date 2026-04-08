<?php

use App\Facades\LibrenmsConfig;
use LibreNMS\RRD\RrdDefinition;

$ipmi_rows = dbFetchRows("SELECT * FROM sensors WHERE device_id = ? AND poller_type='ipmi'", [$device['device_id']]);

if (is_array($ipmi_rows)) {
    d_echo($ipmi_rows);

    if ($ipmi_hostname = DeviceCache::getPrimary()->getAttrib('ipmi_hostname')) {
        $deviceModel = DeviceCache::getPrimary();
        $ipmiSecret = $deviceModel->getSecrets()->ipmi();

        $ipmi_port = filter_var($deviceModel->getAttrib('ipmi_port'), FILTER_VALIDATE_INT) ?: '623';
        $ipmi_timeout = filter_var($deviceModel->getAttrib('ipmi_timeout'), FILTER_VALIDATE_INT) ?: '3';
        $ipmi_kg_key = $deviceModel->getAttrib('ipmi_kg_key');
        $ipmi_ciphersuite = $deviceModel->getAttrib('ipmi_ciphersuite');
        $ipmi_type = $deviceModel->getAttrib('ipmi_type');

        echo 'Fetching IPMI sensor data...';

        $cmd = [LibrenmsConfig::get('ipmitool', 'ipmitool')];
        if (LibrenmsConfig::get('own_hostname') != $device['hostname'] || $ipmi_hostname != 'localhost') {
            array_push($cmd, '-H', $ipmi_hostname, '-U', $ipmiSecret->username, '-P', $ipmiSecret->password, '-L', $ipmiSecret->auth_level, '-p', $ipmi_port);

            if (! empty($ipmi_kg_key)) {
                array_push($cmd, '-y', $ipmi_kg_key);
            }
            if (! empty($ipmi_ciphersuite)) {
                array_push($cmd, '-C', $ipmi_ciphersuite);
            }
            if (! empty($ipmi_timeout)) {
                array_push($cmd, '-N', $ipmi_timeout);
            }
        }

        // Check to see if we know which IPMI interface to use
        // so we dont use wrong arguments for ipmitool
        if ($ipmi_type != '') {
            array_push($cmd, '-I', $ipmi_type, '-c', 'sdr');
            $results = trim((string) external_exec($cmd));
            d_echo($results);
            echo " done.\n";
        } else {
            echo " type not yet discovered.\n";
        }

        foreach (explode("\n", (string) $results) as $row) {
            [$desc, $value, $type, $status] = explode(',', $row);
            $desc = trim($desc, ' ');
            $ipmi_unit_type = LibrenmsConfig::get("ipmi_unit.$type");

            // SDR records can include hexadecimal values, identified by an h
            // suffix (like "93h" for 0x93). Convert them to decimal.
            if (preg_match('/^([0-9A-Fa-f]+)h$/', $value, $matches)) {
                $value = hexdec($matches[1]);
            }

            $ipmi_sensor[$desc][$ipmi_unit_type]['value'] = $value;
            $ipmi_sensor[$desc][$ipmi_unit_type]['unit'] = $type;
        }

        foreach ($ipmi_rows as $ipmisensors) {
            echo 'Updating IPMI sensor ' . $ipmisensors['sensor_descr'] . '... ';

            $sensor_value = $ipmi_sensor[$ipmisensors['sensor_descr']][$ipmisensors['sensor_class']]['value'];
            $unit = $ipmi_sensor[$ipmisensors['sensor_descr']][$ipmisensors['sensor_class']]['unit'];

            echo "$sensor_value $unit\n";

            $rrd_name = get_sensor_rrd_name($device, $ipmisensors);
            $rrd_def = RrdDefinition::make()->addDataset('sensor', 'GAUGE', -20000, 20000);

            $fields = [
                'sensor' => $sensor_value,
            ];

            $tags = [
                'sensor_class' => $ipmisensors['sensor_class'],
                'sensor_type' => $ipmisensors['sensor_type'],
                'sensor_descr' => $ipmisensors['sensor_descr'],
                'sensor_index' => $ipmisensors['sensor_index'],
                'rrd_name' => $rrd_name,
                'rrd_def' => $rrd_def,
            ];
            app('Datastore')->put($device, 'ipmi', $tags, $fields);

            // FIXME warnings in event & mail not done here yet!
            dbUpdate(
                ['sensor_current' => $sensor_value,
                    'lastupdate' => ['NOW()'], ],
                'sensors',
                'poller_type = ? AND sensor_class = ? AND sensor_id = ?',
                ['ipmi', $ipmisensors['sensor_class'], $ipmisensors['sensor_id']]
            );
        }

        unset($ipmi_sensor);
    }
}

<?php

use App\Facades\LibrenmsConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// IPMI - We can discover this on poll!
if ($ipmi_hostname = DeviceCache::getPrimary()->getAttrib('ipmi_hostname')) {
    $deviceModel = DeviceCache::getPrimary();
    $ipmiSecret = $deviceModel->getSecrets()->ipmi();

    echo 'IPMI : ';
    $ipmi_port = filter_var($deviceModel->getAttrib('ipmi_port'), FILTER_VALIDATE_INT) ?: '623';
    $ipmi_timeout = filter_var($deviceModel->getAttrib('ipmi_timeout'), FILTER_VALIDATE_INT) ?: '3';
    $ipmi_kg_key = $deviceModel->getAttrib('ipmi_kg_key');
    $ipmi_ciphersuite = $deviceModel->getAttrib('ipmi_ciphersuite');
    $ipmi_type = $deviceModel->getAttrib('ipmi_type');

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

    foreach (LibrenmsConfig::get('ipmi.type', []) as $ipmi_type) {
        // Check if the IPMI type is available, catch segfaults of ipmitool/freeipmi.
        try {
            Log::debug('Trying IPMI type: ' . $ipmi_type);
            $results = explode(PHP_EOL, (string) external_exec(array_merge($cmd, ['-I', $ipmi_type, 'sensor'])));

            $results = array_values(array_filter($results, fn ($line) => ! Str::contains($line, 'discrete') && trim((string) $line) !== ''));

            if (! empty($results)) {
                $deviceModel->setAttrib('ipmi_type', $ipmi_type);
                echo "$ipmi_type ";
                break;
            }
        } catch (\Exception $e) {
            Log::error('IPMI Discovery error occurred: ' . $e->getMessage());
        }
    }

    $index = 0;

    sort($results);
    foreach ($results as $sensor) {
        // BB +1.1V IOH     | 1.089      | Volts      | ok    | na        | 1.027     | 1.054     | 1.146     | 1.177     | na
        $values = array_map(trim(...), explode('|', (string) $sensor));
        [$desc,$current,$unit,$state,$low_nonrecoverable,$low_limit,$low_warn,$high_warn,$high_limit,$high_nonrecoverable] = $values;

        $index++;
        if ($current != 'na' && LibrenmsConfig::has("ipmi_unit.$unit")) {
            discover_sensor(
                null,
                LibrenmsConfig::get("ipmi_unit.$unit"),
                $device,
                $desc,
                $index,
                'ipmi',
                $desc,
                '1',
                '1',
                $low_limit == 'na' ? null : $low_limit,
                $low_warn == 'na' ? null : $low_warn,
                $high_warn == 'na' ? null : $high_warn,
                $high_limit == 'na' ? null : $high_limit,
                $current,
                'ipmi'
            );
        }
    }

    echo "\n";
}

$sensorDiscovery = app('sensor-discovery');
$sensorDiscovery->sync(sensor_class: 'voltage', poller_type: 'ipmi');
$sensorDiscovery->sync(sensor_class: 'temperature', poller_type: 'ipmi');
$sensorDiscovery->sync(sensor_class: 'fanspeed', poller_type: 'ipmi');
$sensorDiscovery->sync(sensor_class: 'power', poller_type: 'ipmi');
$sensorDiscovery->sync(sensor_class: 'current', poller_type: 'ipmi');
$sensorDiscovery->sync(sensor_class: 'load', poller_type: 'ipmi');

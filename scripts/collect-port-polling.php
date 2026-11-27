#!/usr/bin/env php
<?php

use App\Facades\DeviceCache;
use App\Facades\LibrenmsConfig;
use App\Models\Device;
use Illuminate\Support\Str;
use LibreNMS\Util\Debug;
use Illuminate\Support\Facades\DB;
use LibreNMS\Data\Store\Datastore;
use LibreNMS\Modules\Ports;
use LibreNMS\OS;

$install_dir = realpath(__DIR__ . '/..');
chdir($install_dir);

require $install_dir . '/includes/init.php';
$options = getopt('dh:e:', ['help']);

LibrenmsConfig::set('rrd.enable', false);
// Legacy modules resolve the datastore from the container.
$datastore = new Datastore([]);
app()->instance('Datastore', $datastore);

function print_help()
{
    echo "-h <device id> | <device hostname wildcard>  Poll single device, wildcard hostname, or comma separated list\n";
    echo "-e <percentage>                              Enable/disable selected ports polling for devices which would benefit <percentage> from a change\n";
    echo "\n";
}

if (isset($options['d'])) {
    Debug::set();
}

if (isset($options['help'])) {
    print_help();
    exit(0);
}

$devices = Device::query()->isActive()->orderBy('hostname');
if (isset($options['h'])) {
    if (is_numeric($options['h'])) {
        $devices->where('device_id', $options['h']);
    } elseif (Str::contains($options['h'], ',')) {
        $device_ids = array_map(trim(...), explode(',', $options['h']));
        $device_ids = array_filter($device_ids, is_numeric(...));
        $devices->whereIn('device_id', $device_ids);
    } else {
        $devices->where('hostname', 'like', str_replace('*', '%', $options['h']));
    }
}

$devices = $devices->withCount([
    'ports',
    'ports as inactive_count' => fn ($q) => $q->where('deleted', 1)->orWhere('ifAdminStatus', '!=', 'up')->orWhere('disabled', 1),
]);

$devices = $devices->get()->toArray();

if (isset($options['e'])) {
    if (! is_numeric($options['e']) || $options['e'] < 0) {
        print_help();
        exit(1);
    }
    $enable_sel_value = $options['e'];
}

$module = new Ports;
foreach ($devices as $index => $entry) {
    DeviceCache::setPrimary($entry['device_id']);
    $device = DeviceCache::getPrimary();
    $attributes = $device->attributesToArray();
    $os = OS::make($attributes);
    if (! $device->ports()->isValid()->exists() || ! $module->shouldPoll($os)) {
        unset($devices[$index]);
        continue;
    }

    echo "Polling $device->hostname" . PHP_EOL;
    foreach (['full_time_sec' => false, 'selective_time_sec' => true] as $column => $selected) {
        $quiet = ! Debug::isEnabled();
        if ($quiet) {
            ob_start();
        }
        DB::beginTransaction();
        try {
            // Both runs see the same stored counters and statuses, including device-level overrides.
            $device->setAttrib('selected_ports', $selected ? 'true' : 'false');
            $start = hrtime(true);
            $module->poll($os, $datastore);
            $devices[$index][$column] = (hrtime(true) - $start) / 1e9;
        } finally {
            DB::rollBack();
            $device->unsetRelation('attribs');
            if ($quiet) {
                ob_end_clean();
            }
        }
    }
}
if (! $devices) {
    echo 'No pollable devices with enabled ports.' . PHP_EOL;
    exit(0);
}

$set_count = 0;
foreach ($devices as &$device) {
    $device['inactive_ratio'] = ($device['inactive_count'] / $device['ports_count']);
    $device['diff_sec'] = $device['selective_time_sec'] - $device['full_time_sec'];
    $device['diff_perc'] = 100 * $device['diff_sec'] / $device['full_time_sec'];

    // $enable_sel_value is negative and we want to enable it for all devices with an even lower value.
    // It also has to save more than 1 s, or we might enable it for devices with i.e. 100ms vs 50ms, which isn't needed.
    $device['set'] = 'none';
    if (isset($enable_sel_value) && $device['diff_perc'] < ($enable_sel_value * -1) && $device['diff_sec'] < -1) {
        DeviceCache::get((int) $device['device_id'])->setAttrib('selected_ports', 'true');
        $device['set'] = 'true';
        $set_count++;
    }
    if (isset($enable_sel_value) && $device['diff_perc'] > $enable_sel_value && $device['diff_sec'] > 1) {
        DeviceCache::get((int) $device['device_id'])->setAttrib('selected_ports', 'false');
        $device['set'] = 'false';
        $set_count++;
    }
}
unset($device);  // will edit the wrong thing after using $device by reference

// print out the results
$stats = [
    'device_id',
    'os',
    'port_count',
    'inactive_ratio',
    'full_time',
    'selective_time',
    'diff',
    'diff',
    'set',
];

echo PHP_EOL;
$header = "| %9.9s | %-11.11s | %10.10s | %14.14s | %10.10s | %14.14s | %8.10s | %5.9s | %5.5s |\n";
call_user_func_array(printf(...), array_merge([$header], $stats));

$mask = "| %9.9s | %-11.11s | %10.10s | %14.3f | %9.3fs | %13.3fs | %s%+7.3fs\e[0m | %s%+4.0f%%\e[0m | %5.5s |\n";
foreach ($devices as $device) {
    $diff_color = ($device['diff_sec'] > 0 ? "\033[0;31m" : "\033[0;32m");
    printf(
        $mask,
        $device['device_id'],
        $device['os'],
        $device['ports_count'],
        $device['inactive_ratio'],
        $device['full_time_sec'],
        $device['selective_time_sec'],
        $diff_color,
        $device['diff_sec'],
        $diff_color,
        $device['diff_perc'],
        $device['set']
    );
}

$total_ports = array_sum(array_column($devices, 'ports_count'));
$inactive_ratio = array_sum(array_column($devices, 'inactive_ratio')) / count($devices);
$total_full_time = array_sum(array_column($devices, 'full_time_sec'));
$total_selective_time = array_sum(array_column($devices, 'selective_time_sec'));
$difference = $total_selective_time - $total_full_time;
$difference_perc = 100 * $difference / $total_full_time;
$total_diff_color = ($difference > 0 ? "\033[0;31m" : "\033[0;32m");

printf(
    $mask,
    'Totals:',
    '',
    $total_ports,
    $inactive_ratio,
    $total_full_time,
    $total_selective_time,
    $total_diff_color,
    $difference,
    $total_diff_color,
    $difference_perc,
    $set_count
);

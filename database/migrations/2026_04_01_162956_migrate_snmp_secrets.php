<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate device secrets
        DB::table('devices')
            ->orderBy('devices.device_id')->chunk(100, function ($devices) {
                $pollingMethods = [];
                foreach ($devices as $device) {
                    // SNMP
                    $snmpver = $device->snmpver ?? 'v2c';
                    $data = ['version' => $snmpver];

                    if ($snmpver === 'v3') {
                        $data['authlevel'] = $device->authlevel ?? 'noAuthNoPriv';
                        $data['authname'] = $device->authname ?? '';
                        $data['authpass'] = $device->authpass ?? null;
                        $data['authalgo'] = $device->authalgo ?? 'MD5';
                        $data['cryptopass'] = $device->cryptopass ?? null;
                        $data['cryptoalgo'] = $device->cryptoalgo ?? 'AES';
                    } else {
                        $data['community'] = $device->community ?? 'public';
                    }

                    $secretId = DB::table('secrets')->insertGetId([
                        'description' => "SNMP for device $device->hostname",
                        'secret_type' => 'snmp',
                        'default' => false,
                        'data' => encrypt(json_encode($data)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $pollingMethods[] = [
                        'device_id' => $device->device_id,
                        'method_type' => 'snmp',
                        'enabled' => ! $device->snmp_disable,
                        'affects_availability' => true,
                        'secret_id' => $secretId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('device_polling_methods')->insert($pollingMethods);
            });
    }
};

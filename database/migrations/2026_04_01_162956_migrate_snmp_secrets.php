<?php

use Illuminate\Contracts\Encryption\EncryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $snmpConfig = \App\Facades\LibrenmsConfig::get('snmp');
        $globalSecrets = [];

        // Import global v1/v2c communities
        if (isset($snmpConfig['community']) && is_array($snmpConfig['community'])) {
            foreach ($snmpConfig['community'] as $index => $community) {
                if (! empty($community)) {
                    try {
                        $data = ['version' => 'v1', 'community' => $community];
                        $id = DB::table('secrets')->insertGetId([
                            'description' => 'Global SNMP v1 Community #' . ($index + 1),
                            'secret_type' => 'snmp',
                            'default' => true,
                            'data' => encrypt(json_encode($data)),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $globalSecrets[md5(json_encode($data))] = $id;
                    } catch (EncryptException) {
                    }
                    try {
                        $data = ['version' => 'v2c', 'community' => $community];
                        $id = DB::table('secrets')->insertGetId([
                            'description' => 'Global SNMP v2c Community #' . ($index + 1),
                            'secret_type' => 'snmp',
                            'default' => true,
                            'data' => encrypt(json_encode($data)),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $globalSecrets[md5(json_encode($data))] = $id;
                    } catch (EncryptException) {
                    }
                }
            }
        }

        // Import global v3 users
        if (isset($snmpConfig['v3']) && is_array($snmpConfig['v3'])) {
            foreach ($snmpConfig['v3'] as $v3) {
                try {
                    $data = [
                        'version' => 'v3',
                        'authlevel' => $v3['authlevel'] ?? 'noAuthNoPriv',
                        'authname' => $v3['authname'] ?? '',
                        'authpass' => $v3['authpass'] ?? null,
                        'authalgo' => $v3['authalgo'] ?? 'MD5',
                        'cryptopass' => $v3['cryptopass'] ?? null,
                        'cryptoalgo' => $v3['cryptoalgo'] ?? 'AES',
                    ];
                    $id = DB::table('secrets')->insertGetId([
                        'description' => 'Global SNMP v3 User: ' . ($v3['authname'] ?? '<unnamed>'),
                        'secret_type' => 'snmp',
                        'default' => true,
                        'data' => encrypt(json_encode($data)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $globalSecrets[md5(json_encode($data))] = $id;
                } catch (EncryptException) {
                }
            }
        }

        // Migrate device secrets
        DB::table('devices')->orderBy('device_id')->chunk(100, function ($devices) use ($globalSecrets) {
            foreach ($devices as $device) {
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

                try {
                    $hash = md5(json_encode($data));
                    if (isset($globalSecrets[$hash])) {
                        $secretId = $globalSecrets[$hash];
                    } else {
                        // Create per-device secret
                        $description = 'SNMP for device ' . $device->hostname;
                        $secretId = DB::table('secrets')->insertGetId([
                            'description' => $description,
                            'secret_type' => 'snmp',
                            'default' => false,
                            'data' => json_encode($data),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('device_secrets')->updateOrInsert(
                        ['device_id' => $device->device_id, 'secret_type' => 'snmp'],
                        ['secret_id' => $secretId]
                    );
                } catch (EncryptException) {
                }
            }
        });
    }
};

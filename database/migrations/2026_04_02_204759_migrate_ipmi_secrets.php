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
        $attributes = [
            'ipmi_hostname',
            'ipmi_port',
            'ipmi_username',
            'ipmi_password',
            'ipmi_kg_key',
            'ipmi_ciphersuite',
            'ipmi_timeout',
            'ipmi_type',
        ];

        DB::table('devices_attribs')
            ->whereIn('attrib_type', $attributes)
            ->orderBy('device_id')
            ->chunk(100, function ($rows) {
                $devices = [];
                foreach ($rows as $row) {
                    $devices[$row->device_id][$row->attrib_type] = $row->attrib_value;
                }

                foreach ($devices as $deviceId => $attribs) {
                    if (empty($attribs['ipmi_hostname'])) {
                        continue;
                    }

                    $data = [
                        'username' => $attribs['ipmi_username'] ?? '',
                        'password' => $attribs['ipmi_password'] ?? '',
                        'auth_level' => 'USER', // default in ipmi.inc.php was -L USER
                        'auth_type' => 'NONE', // default in IpmiSecret
                    ];

                    try {
                        $hostname = DB::table('devices')->where('device_id', $deviceId)->value('hostname');
                        $description = "IPMI for device " . ($hostname ?: $deviceId);

                        $secretId = DB::table('secrets')->insertGetId([
                            'description' => $description,
                            'secret_type' => 'ipmi',
                            'default' => false,
                            'data' => encrypt(json_encode($data)),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        DB::table('device_secrets')->updateOrInsert(
                            ['device_id' => $deviceId, 'secret_type' => 'ipmi'],
                            ['secret_id' => $secretId]
                        );

                        DB::table('devices_attribs')
                            ->where('device_id', $deviceId)
                            ->whereIn('attrib_type', ['ipmi_username', 'ipmi_password'])
                            ->delete();
                    } catch (EncryptException) {
                        // ignore
                    }
                }
            });
    }
};

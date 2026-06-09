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
            'ipmi_username',
            'ipmi_password',
            'ipmi_kg_key',
        ];

        DB::table('devices_attribs')
            ->whereIn('attrib_type', $attributes)
            ->orderBy('device_id')
            ->chunk(100, function ($rows) {
                $devices = [];
                foreach ($rows as $row) {
                    $devices[$row->device_id][$row->attrib_type] = $row->attrib_value;
                }

                $pollingMethods = [];
                foreach ($devices as $deviceId => $attribs) {
                    if (empty($attribs['ipmi_hostname'])) {
                        continue;
                    }

                    $data = [
                        'username' => $attribs['ipmi_username'] ?? '',
                        'password' => $attribs['ipmi_password'] ?? '',
                        'kg_key' => $attribs['ipmi_kg_key'] ?? null,
                    ];

                    try {
                        $hostname = DB::table('devices')->where('device_id', $deviceId)->value('hostname');
                        $description = "IPMI for device " . $hostname;

                        $secretId = DB::table('secrets')->insertGetId([
                            'description' => $description,
                            'secret_type' => 'ipmi',
                            'default' => false,
                            'data' => encrypt(json_encode($data)),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $pollingMethods[] = [
                            'device_id' => $deviceId,
                            'method_type' => 'ipmi',
                            'enabled' => true,
                            'affects_availability' => false,
                            'secret_id' => $secretId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        DB::table('devices_attribs')
                            ->where('device_id', $deviceId)
                            ->whereIn('attrib_type', ['ipmi_username', 'ipmi_password'])
                            ->delete();
                    } catch (EncryptException) {
                        // ignore
                    }
                }
                DB::table('device_polling_methods')->insert($pollingMethods);
            });
    }
};

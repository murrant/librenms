<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('devices', 'snmpver')) {
            DB::table('devices')->chunkById(100, function ($devices) {
                foreach ($devices as $device) {
                    // Migrate SNMP
                    if ($device->snmpver !== 'nan') {
                        $snmp_creds = [
                            'version' => $device->snmpver,
                            'community' => $device->community,
                            'auth_level' => $device->authlevel,
                            'auth_name' => $device->authname,
                            'auth_pass' => $device->authpass,
                            'auth_algo' => $device->authalgo,
                            'crypto_pass' => $device->cryptopass,
                            'crypto_algo' => $device->cryptoalgo,
                            'port' => $device->port,
                            'transport' => $device->transport,
                        ];

                        $credential_id = DB::table('credentials')->insertGetId([
                            'description' => "SNMP for {$device->hostname}",
                            'credential_type' => 'snmp',
                            'default' => false,
                            'data' => json_encode($snmp_creds),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        DB::table('device_credential')->insert([
                            'device_id' => $device->device_id,
                            'credential_id' => $credential_id,
                            'credential_type' => 'snmp',
                        ]);
                    }
                }
            }, 'device_id');

            // Drop legacy columns
            Schema::table('devices', function (Blueprint $table) {
                $table->dropColumn([
                    'community',
                    'authlevel',
                    'authname',
                    'authpass',
                    'authalgo',
                    'cryptopass',
                    'cryptoalgo',
                    'snmpver',
                    'port',
                    'transport',
                ]);
            });
        }
    }
};

<?php

use App\Models\Device;
use Illuminate\Database\Migrations\Migration;

class MigrateSnmpCredentials extends Migration
{
    /**
     * @var \App\Models\Credential[]
     */
    private $credentials = [];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Device::query()->chunk(100, function ($devices) {
            foreach ($devices as $device) {
                $key = implode('_', $device->only([
                    'snmpver',
                    'transport',
                    'port',
                    'community',
                    'authlevel',
                    'authname',
                    'authalgo',
                    'authpass',
                    'cryptoalgo',
                    'cryptopass',
                ]));

                if (isset($this->credentials[$key])) {
                    $credential = $this->credentials[$key];
                } else {
                    $credential = new \App\Models\SnmpCredential;
                    $credential->description = $key;
                    $credential->credentials = [
                        'version' => $device->snmpver,
                        'community' => $device->community,
                        'level' => $device->authlevel,
                        'auth_name' => $device->authname,
                        'auth_pass' => $device->authpass,
                        'auth_algo' => $device->authalgo,
                        'crypto_pass' => $device->cryptopass,
                        'crypto_algo' => $device->cryptoalgo,
                        'transport' => $device->transport,
                        'port' => $device->port,
                    ];
                    $credential->save();
                    $this->credentials[$key] = $credential;
                }

                $device->snmp_credential_id = $credential->id;
                $device->save();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // dangerous
//        Device::with('snmpCredentials')->chunk(100, function ($devices) {
//            foreach ($devices as $device) {
//                $device->snmpver = $device->snmpCredentials->version;
//                $device->community = $device->snmpCredentials->community;
//                $device->authlevel = $device->snmpCredentials->level;
//                $device->authname = $device->snmpCredentials->auth_name;
//                $device->authpass = $device->snmpCredentials->auth_pass;
//                $device->authalgo = $device->snmpCredentials->auth_algo;
//                $device->cryptopass = $device->snmpCredentials->crypto_pass;
//                $device->cryptoalgo = $device->snmpCredentials->crypto_algo;
//                $device->transport = $device->snmpCredentials->transport;
//                $device->port = $device->snmpCredentials->port;
//            }
//        });
    }

}

<?php

namespace App\Console\Commands;

use App\Facades\LibrenmsConfig;
use App\Models\Credential;
use App\Models\Device;
use App\Repositories\CredentialRepository;
use Illuminate\Console\Command;
use LibreNMS\Credentials\PowerDnsCredentialType;
use LibreNMS\Credentials\SnmpV2cCredentialType;
use LibreNMS\Credentials\SnmpV3CredentialType;

class CredentialMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credential:migrate-existing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing credentials to secure storage';

    public function __construct(protected CredentialRepository $credentialRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting migration of credentials...');

        $globalCredentials = collect();

        // Migrate global SNMP v2c communities
        $communities = (array) LibrenmsConfig::get('snmp.community', []);
        foreach ($communities as $index => $community) {
            $name = "Global v2c - $index";
            $credential = Credential::firstOrCreate(
                ['name' => $name],
                [
                    'type' => SnmpV2cCredentialType::class,
                    'data' => ['community' => $community],
                    'is_default' => true,
                ]
            );

            $globalCredentials->push($credential);
            $this->line("Migrated global SNMP v2c: $name");
        }

        // Migrate global SNMP v3 credentials
        $v3Config = (array) LibrenmsConfig::get('snmp.v3', []);
        if (! empty($v3Config)) {
            // snmp.v3 can be a single credential or an array of them
            if (isset($v3Config['authname'])) {
                $v3Config = [$v3Config];
            }

            foreach ($v3Config as $index => $v3) {
                if (empty($v3['authname'])) {
                    continue;
                }

                $name = "Global v3 - $index";
                $credential = Credential::firstOrCreate(
                    ['name' => $name],
                    [
                        'type' => SnmpV3CredentialType::class,
                        'data' => [
                            'authname' => $v3['authname'] ?? '',
                            'authlevel' => $v3['authlevel'] ?? 'noAuthNoPriv',
                            'authalgo' => $v3['authalgo'] ?? 'SHA',
                            'authpass' => $v3['authpass'] ?? '',
                            'cryptoalgo' => $v3['cryptoalgo'] ?? 'AES',
                            'cryptopass' => $v3['cryptopass'] ?? '',
                        ],
                        'is_default' => true,
                    ]
                );

                $globalCredentials->push($credential);
                $this->line("Migrated global SNMP v3: $name");
            }
        }

        // Migrate global PowerDNS credentials
        $pdnsApiKey = LibrenmsConfig::get('apps.powerdns-recursor.api-key');
        if (! empty($pdnsApiKey)) {
            $name = 'Global PowerDNS Recursor';
            $credential = Credential::firstOrCreate(
                ['name' => $name],
                [
                    'type' => PowerDnsCredentialType::class,
                    'data' => [
                        'api_key' => $pdnsApiKey,
                        'port' => LibrenmsConfig::get('apps.powerdns-recursor.port', 8082),
                        'https' => LibrenmsConfig::get('apps.powerdns-recursor.https', false),
                    ],
                    'is_default' => true,
                ]
            );

            $globalCredentials->push($credential);
            $this->line("Migrated global PowerDNS: $name");
        }

        $this->info('Starting migration of device credentials...');

        Device::all()->each(function (Device $device) use ($globalCredentials) {
            if ($device->snmpver === 'v2c' && ! empty($device->community)) {
                $deviceData = ['community' => $device->community];

                // Check if it matches any global v2c credential
                $matched = $globalCredentials->first(function ($cred) use ($deviceData) {
                    return $cred->type === SnmpV2cCredentialType::class &&
                        $this->credentialRepository->dataMatches($cred->type, $cred->data, $deviceData);
                });

                if ($matched) {
                    $this->line("Device {$device->hostname} uses global v2c credential, associating.");
                    $device->secureCredentials()->syncWithoutDetaching([$matched->id => ['order' => 1]]);

                    return;
                }

                $name = "v2c-{$device->hostname}";
                $credential = Credential::firstOrCreate(
                    ['name' => $name],
                    [
                        'type' => SnmpV2cCredentialType::class,
                        'data' => $deviceData,
                    ]
                );
                $device->secureCredentials()->syncWithoutDetaching([$credential->id => ['order' => 1]]);
                $this->line("Migrated SNMP v2c for {$device->hostname}");
            } elseif ($device->snmpver === 'v3' && ! empty($device->authname)) {
                $deviceData = [
                    'authname' => $device->authname,
                    'authlevel' => $device->authlevel,
                    'authalgo' => $device->authalgo,
                    'authpass' => $device->authpass,
                    'cryptoalgo' => $device->cryptoalgo,
                    'cryptopass' => $device->cryptopass,
                ];

                // Check if it matches any global v3 credential
                $matched = $globalCredentials->first(function ($cred) use ($deviceData) {
                    return $cred->type === SnmpV3CredentialType::class &&
                        $this->credentialRepository->dataMatches($cred->type, $cred->data, $deviceData);
                });

                if ($matched) {
                    $this->line("Device {$device->hostname} uses global v3 credential, associating.");
                    $device->secureCredentials()->syncWithoutDetaching([$matched->id => ['order' => 1]]);

                    return;
                }

                $name = "v3-{$device->hostname}";
                $credential = Credential::firstOrCreate(
                    ['name' => $name],
                    [
                        'type' => SnmpV3CredentialType::class,
                        'data' => $deviceData,
                    ]
                );
                $device->secureCredentials()->syncWithoutDetaching([$credential->id => ['order' => 1]]);
                $this->line("Migrated SNMP v3 for {$device->hostname}");
            }
        });

        $this->info('Migration complete.');
    }
}

<?php

namespace App\Console\Commands;

use App\Actions\Device\ValidateNewDevice;
use App\Console\LnmsCommand;
use App\Facades\DeviceCache;
use App\Facades\LibrenmsConfig;
use App\Models\Device;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LibreNMS\Util\Compare;
use LibreNMS\Util\Oid;
use LibreNMS\Util\Validate;
use SnmpQuery;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeOsDetection extends LnmsCommand
{
    protected $name = 'make:os-detection';
    protected $description = 'Interactively build an OS detection YAML entry.';

    private string $cacheKey = '';
    private bool $cacheEnabled = true;
    private array $detection = [
        'os' => '',
        'text' => '',
        'type' => 'network',
        'icon' => 'generic',
        'discovery' => [],
    ];
    private array $state = [
        'method' => 'sysObjectID',
        'match' => '',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->addOption('device', null, InputOption::VALUE_REQUIRED);
        $this->addOption('os', null, InputOption::VALUE_REQUIRED);
        $this->addOption('text', null, InputOption::VALUE_REQUIRED);
        $this->addOption('type', null, InputOption::VALUE_REQUIRED);
        $this->addOption('icon', null, InputOption::VALUE_REQUIRED);
        $this->addOption('group', null, InputOption::VALUE_REQUIRED);
        $this->addOption('mib-dir', null, InputOption::VALUE_REQUIRED);
        $this->addOption('dry-run', null, InputOption::VALUE_NONE);
        $this->addOption('overwrite', null, InputOption::VALUE_NONE);
        $this->addOption('no-testdata', null, InputOption::VALUE_NONE);
        $this->addOption('non-interactive', null, InputOption::VALUE_NONE);
        $this->addOption('no-cache', null, InputOption::VALUE_NONE);
    }

    public function handle(): int
    {
        $this->configureOutputOptions();

        $this->cacheEnabled = ! $this->option('no-cache');

        try {
            $device = $this->getDevice();
            $this->loadState($device);
            $this->collectMetadata($device);
            $this->collectDiscovery($device);
            $this->writeResult();

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function getDevice(): Device
    {
        $id = $this->option('device') ?? text('Enter device ID, hostname or IP');
        $device = DeviceCache::get($id);

        if ($device->exists) {
            return $device;
        }

        if (Validate::ipOrHostname($id)) {
            $device = new Device(['hostname' => $id]);
            $validated = (new ValidateNewDevice($device))->execute();
            if ($validated) {
                return $device;
            }

            throw new Exception("Could not detect credentials for $id");
        }

        throw new Exception("Device not found: $id");
    }

    // ------------------------- Cache + Preload -----------------------------
    private function loadState(Device $device): void
    {
        $this->cacheKey = "make-os-detection:{$device->device_id}";
        dump(Cache::get($this->cacheKey));

        if ($this->cacheEnabled) {
            if (Cache::has($this->cacheKey) && confirm('Resume previous session?')) {
                [$this->detection, $this->state] = Cache::get($this->cacheKey, []);

                return; // loaded, no need to check existing os
            }
        }

        $this->loadStateFromExistingOs($device);
    }

    private function saveState(): void
    {
        if ($this->cacheEnabled) {
            Cache::put($this->cacheKey, [$this->detection, $this->state] , now()->addDay());
        }
    }

    private function askValue(string $key, callable $question, ?string $default = null): ?string
    {
        // determine if this is a non-detection state value
        $field = isset($this->state[$key]) ? 'state' : 'detection';

        if ($this->hasOption($key) && $this->option($key)) {
            return $this->saveValue($field, $key, $this->option($key));
        }

        if (Arr::has($this->{$field}, $key)) {
            $default = Arr::get($this->{$field}, $key, $default);
        }

        return $this->saveValue($field, $key, $question((string) $default));
    }

    private function saveValue(string $field, string $key, ?string $value): ?string
    {
        Arr::set($this->{$field}, $key, $value);
        $this->saveState();

        return $value;
    }

    private function loadStateFromExistingOs(Device $device): void
    {
        if ($device->os == 'generic') {
            return;
        }

        if (confirm("OS ($device->os) already exists for $device->hostname. Preload as starting point?")) {
            $this->detection = LibrenmsConfig::get("os.{$device->os}", []);

            if (isset($this->detection['discovery']) && is_array($this->detection['discovery']) && count($this->detection['discovery']) == 1) {
                if (isset($this->detection['discovery']['snmpget'])) {
                    $this->state['method'] = 'snmpget';
                } elseif (isset($this->detection['discovery']['sysDescr'])) {
                    $this->state['method'] = 'sysDescr';
                } else {
                    $this->state['method'] = 'sysObjectID';
                }
            }
        }
    }
    // ------------------------- Metadata -----------------------------

    private function collectMetadata(Device $device): void
    {
        $this->askValue(
            'os',
            fn ($default) => text('Enter OS slug', default: $default),
            $this->derive('os', $device),
        );
        $this->askValue(
            'text',
            fn ($default) => text('Enter display text',  default: $default),
            $this->derive('text', $device),
        );
        $this->askValue(
            'type',
            fn ($default) => text('Enter device type',  default: $default),
            $this->derive('type', $device),
        );
        $this->askValue(
            'icon',
            fn ($default) => text('Enter icon name',  default: $default),
            $this->derive('icon', $device),
        );
    }

    // ----------------------- Discovery -------------------------------

    private function collectDiscovery(Device $device): void
    {
        $this->askValue('method', fn ($default) => select('Select detection method', [
            'sysObjectID' => 'sysObjectID (best)',
            'sysDescr' => 'sysDescr',
            'snmpget' => 'snmpget (slowest)',
        ], $default));

        match ($this->state['method']) {
            'sysDescr'   => $this->buildSysDescrDiscovery($device->sysDescr),
            'snmpget'    => $this->buildSnmpGetDiscovery($device),
            default      => $this->buildSysObjectIdDiscovery($device->sysObjectID),
        };
    }

    private function buildSysObjectIdDiscovery(string $sysObjectID): void
    {
        Arr::forget($this->detection, ['discovery.0.sysDescr', 'discovery.0.sysDescr_regex', 'discovery.0.snmpget']);
        info("sysObjectID: $sysObjectID");

        $match_type = $this->askValue('match', fn($default) => select(
            label: 'Select match type',
            options: [
                'starts' => 'starts with',
                'regex' => 'regex match',
            ],
            default: $default,
        ));

        if ($match_type == 'regex') {
            $field = 'discovery.0.sysObjectID_regex.0';
            Arr::forget($this->detection, 'discovery.0.sysObjectID');
        } else {
            $field = 'discovery.0.sysObjectID.0';
            Arr::forget($this->detection, 'discovery.0.sysObjectID_regex');
        }

        $this->askValue($field, fn($default) => text(
            label: 'Enter sysObjectID prefix',
            default: $default,
            required: true,
            validate: function ($value) use ($sysObjectID) {
                if (! Oid::of(preg_replace('/\.$/', '', $value))->isNumeric()) {
                    return 'Invalid sysObjectID';
                }

                return str_starts_with($sysObjectID, $value) ? null : "sysObjectID prefix must match $sysObjectID";
            },
        ), $this->suggestSysObjectIdPrefix($sysObjectID));
    }

    private function buildSysDescrDiscovery(string $sysDescr): void
    {
        Arr::forget($this->detection, ['discovery.0.sysObjectID', 'discovery.0.sysObjectID_regex', 'discovery.0.snmpget']);

        info("sysDescr: $sysDescr");

        $match_type = $this->askValue('match', fn($default) => select(
            label: 'Select match type',
            options: [
                'contains' => 'contains',
                'regex' => 'regex match',
            ],
            default: $default,
        ));

        if ($match_type == 'regex') {
            $field = 'discovery.0.sysDescr_regex.0';
            Arr::forget($this->detection, 'discovery.0.sysDescr');
        } else {
            $field = 'discovery.0.sysDescr.0';
            Arr::forget($this->detection, 'discovery.0.sysDescr_regex');
        }

        $this->askValue($field, fn($default) => text(
            label: 'Enter sysDescr substring match or regex',
            default: $default,
            required: true,
            validate: function ($match) use ($sysDescr, $match_type) {
                if ($match_type == 'regex') {
                    if (! Validate::regex($match)) {
                        return 'Invalid regex';
                    }

                    return preg_match($match, $sysDescr) ? null : "Regex did not match $sysDescr";
                }

                return str_contains($sysDescr, $match) ? null : "sysDescr must contain $match";
            },
        ), $this->suggestSysDescrMatch($sysDescr));
    }

    private function buildSnmpGetDiscovery(Device $device): void
    {
        Arr::forget($this->detection, ['discovery.0.sysDescr', 'discovery.0.sysDescr_regex', 'discovery.0.sysObjectID', 'discovery.0.sysObjectID_regex']);

        $fetched_value = '';
        $this->askValue('discovery.0.snmpget.0.oid', function ($default) use (&$fetched_value, $device) {
            return text(
                label: 'Enter SNMP OID',
                default: $default,
                required: true,
                validate: function (string $oid) use (&$fetched_value, $device) {
                    $response = SnmpQuery::device($device)->get($oid);
                    $error = $response->getErrorMessage();
                    $fetched_value = $response->value();

                    return $fetched_value ? null : "OID must return a value: $error";
                },
            );
        });

        info("Fetched value: $fetched_value");

        $op = $this->askValue('discovery.0.snmpget.0.op', fn($default) => select(
            label: 'Select operator',
            options: [
                '=' => 'equals',
                'contains' => 'contains',
                'regex' => 'regex match',
                '!=' => 'not equals',
                '==' => 'strict equals',
                '!==' => 'strict not equals',
                '>=' => 'greater than or equal',
                '<=' => 'less than or equal',
                '>' => 'greater than',
                '<' => 'less than',
                'not_contains' => 'not contains',
                'starts' => 'starts with',
                'not_starts' => 'not starts with',
                'ends' => 'ends with',
                'not_ends' => 'not ends with',
                'not_regex' => 'not regex match',
                'in_array' => 'in array',
                'not_in_array' => 'not in array',
                'exists' => 'returns value',
            ],
            default: $default,
            scroll: 3,
        ));
        $this->askValue('discovery.0.snmpget.0.value', fn($fetched) => text(
            label: 'Enter match string',
            default: $fetched,
            required: true,
            validate: fn ($value) => Compare::values($fetched, $value, $op) ? null : "Value does not match '$fetched'"
        ), $fetched_value);

        // sysObjectID is required for snmpget discovery
        $this->saveValue('detection', 'discovery.0.sysObjectID.0', $device->sysObjectID);

        // sysObjectID first
        krsort($this->detection['discovery'][0]);
    }

    private function renderYaml(): string
    {
        // first sort the data to a specific order
        $order = [
            'os' => 0,
            'text' => 1,
            'type' => 2,
            'icon' => 3,
            'group' => 4,
            'mib_dir' => 5,
            'over' => 6,
            'discovery' => 7,
        ];

        $data = $this->detection;
        uksort($data, function($a, $b) use ($order) {
            $aPriority = $order[$a] ?? PHP_INT_MAX;
            $bPriority = $order[$b] ?? PHP_INT_MAX;

            return $aPriority !== $bPriority
                ? $aPriority <=> $bPriority
                : $a <=> $b;
        });

        return Str::finish(Yaml::dump($data, 10, 4), "\n");
    }

    private function writeResult(): void
    {
        $yaml = $this->renderYaml();
        $file = resource_path("definitions/os_detection/{$this->detection['os']}.yaml");

        if ($this->option('dry-run')) {
            $this->info("Dry run: would write to $file");
            $this->line($yaml);

            return;
        }

        File::put($file, $yaml);

        $this->info("Detection written to $file");
        $this->printNextSteps($this->detection['os']);

        Cache::forget($this->cacheKey);
    }

    // ---------------------- Helpers --------------------------------
    private function derive(string $key, Device $device): ?string
    {
        return match($key) {
            'os' => strtolower(str_replace(' ', '-', substr($device->sysDescr, 0, 10))),
            'text' => ucfirst(explode(' ', $device->sysDescr, 2)[0]),
            'type' => str_contains($device->sysDescr, 'Switch') ? 'switch' : 'network',
            'icon' => 'generic',
            default => null,
        };
    }

    private function suggestSysObjectIdPrefix(string $oid): string
    {
        // Enterprise OID with PEN + family: return first 8 parts (with trailing dot if more exist)
        if (str_starts_with($oid, '.1.3.6.1.4.1.')) {
            $parts = explode('.', $oid);
            if (count($parts) >= 9) {
                $pen = $parts[7];
                $family = $parts[8];
                return count($parts) > 9 ? ".1.3.6.1.4.1.$pen.$family." : ".1.3.6.1.4.1.$pen.$family";
            }
        }

        return $oid;
    }

    private function suggestSysDescrMatch(string $sysDescr): string
    {
        // Heuristic: return the first one or two words (letters/numbers) from sysDescr
        if (preg_match('/[A-Za-z][A-Za-z0-9+._-]*(?:\s+[A-Za-z][A-Za-z0-9+._-]*)?/', $sysDescr, $m)) {
            return $m[0];
        }

        return substr(explode("\n", $sysDescr)[0], 0, 20);
    }

    private function printNextSteps(string $os): void
    {
        $this->line("Next steps:");
        $this->line("  - Edit resources/definitions/os_detection/$os.yaml to adjust details");
        $this->line("  - Run: ./lnms dev:os-test --os=$os");
    }
}

<?php

namespace App\Console\Commands;

use App\Console\LnmsCommand;
use App\Facades\LibrenmsConfig;
use App\Models\Device;
use DeviceCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Exception\JsonDecodingException;
use JsonSchema\Exception\ValidationException;
use LibreNMS\Modules\Core;
use SnmpQuery;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

class MakeOsDetection extends LnmsCommand
{
    protected $name = 'make:os-detection';

    public function __construct()
    {
        parent::__construct();

        // Options from spec (kept minimal but complete)
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
        $this->addOption('method', null, InputOption::VALUE_REQUIRED);
        $this->addOption('reset', null, InputOption::VALUE_NONE);
    }

    public function handle(): int
    {
        $this->configureOutputOptions();

        // 1) Resolve device
        $selector = $this->option('device');
        if (! $selector && $this->option('non-interactive')) {
            $this->error('Missing --device. Provide hostname, IP, or device id.');
            return self::FAILURE;
        }
        if (! $selector) {
            $selector = $this->ask('Device (hostname/IP/id)');
        }

        LibrenmsConfig::invalidateAndReload();
        $device = $this->resolveDevice($selector);
        if (! $device) {
            $this->error('Device not found. Add the device first or check the selector.');
            return self::FAILURE;
        }

        // Resume support: load cached state for this device
        $cacheKey = 'make_os_detection:' . $device->device_id;
        if ($this->option('reset')) {
            Cache::forget($cacheKey);
        }
        $state = Cache::get($cacheKey, []);
        if (! empty($state) && ! $this->option('non-interactive')) {
            $this->info('Loaded previous answers from cache. Use --reset to start over.');
        }

        // 2) Collect SNMP basics
        $sysObjectID = SnmpQuery::make()->device($device)->numeric(true)->get('.1.3.6.1.2.1.1.2.0')->value();
        $sysDescr    = SnmpQuery::make()->device($device)->get('.1.3.6.1.2.1.1.1.0')->value();
        if (! $sysObjectID || ! $sysDescr) {
            $this->error('Failed to read sysObjectID/sysDescr via SNMP. Ensure SNMP is configured and reachable.');
            return self::FAILURE;
        }

        $this->info('sysObjectID: ' . $sysObjectID);
        $this->info('sysDescr: ' . $sysDescr);

        // 3) Check existing definitions
        [$matched, $reasons] = $this->findMatchingOsDefinitions($sysObjectID, $sysDescr);
        if (! empty($matched)) {
            $this->warn('Existing OS definition(s) may match this device: ' . implode(', ', $matched));
            if (! empty($reasons)) {
                foreach ($reasons as $os => $why) {
                    $this->line(" - $os: $why");
                }
            }

            // Preload values from the best matched OS definition before prompting (cache has precedence)
            $bestOs = $matched[0];
            $existing = LibrenmsConfig::get("os.$bestOs");
            if (is_array($existing)) {
                $preload = [];
                $preload['os'] = $existing['os'] ?? $bestOs;
                $preload['text'] = $existing['text'] ?? null;
                $preload['type'] = $existing['type'] ?? null;
                $preload['icon'] = $existing['icon'] ?? null;
                if (! empty($existing['mib_dir'])) {
                    $preload['mib_dir'] = $existing['mib_dir'];
                }

                // Discovery preloading from the first entry
                if (! empty($existing['discovery']) && is_array($existing['discovery'])) {
                    $entry = $existing['discovery'][0] ?? [];
                    if (is_array($entry)) {
                        // If snmpget exists, prefer that method
                        if (isset($entry['snmpget']) && is_array($entry['snmpget'])) {
                            $sg = $entry['snmpget'];
                            if (! isset($state['method'])) { $state['method'] = 'snmpget'; }
                            if (isset($sg['oid']) && ! isset($state['snmpget_oid'])) { $state['snmpget_oid'] = (string) $sg['oid']; }
                            if (isset($sg['op']) && ! isset($state['snmpget_op'])) { $state['snmpget_op'] = (string) $sg['op']; }
                            if (array_key_exists('value', $sg) && ! isset($state['snmpget_value'])) {
                                // Store as string for prompt default if not array/bool
                                $val = $sg['value'];
                                if (is_array($val)) {
                                    $val = implode(',', $val);
                                } elseif (is_bool($val)) {
                                    $val = $val ? 'true' : 'false';
                                }
                                $state['snmpget_value'] = (string) $val;
                            }
                            // Prefer snmpget.mib_dir, fallback to top-level mib_dir
                            if (! isset($state['mib_dir'])) {
                                if (! empty($sg['mib_dir'])) { $state['mib_dir'] = (string) $sg['mib_dir']; }
                                elseif (! empty($existing['mib_dir'])) { $state['mib_dir'] = (string) $existing['mib_dir']; }
                            }
                        }
                        // sysObjectID present: set prefix default if not present
                        if (! isset($state['sysObjectID_prefix'])) {
                            if (isset($entry['sysObjectID'])) {
                                $soid = $entry['sysObjectID'];
                                $first = is_array($soid) ? ($soid[0] ?? null) : $soid;
                                if (is_string($first) && $first !== '') {
                                    $prefix = Str::start($first, '.');
                                    if ($prefix !== $sysObjectID && ! Str::endsWith($prefix, '.')) { $prefix .= '.'; }
                                    $state['sysObjectID_prefix'] = $prefix;
                                }
                            } elseif (isset($entry['sysObjectID_regex'])) {
                                // Not ideal for prompting; skip regex and let normal suggestion handle it
                            }
                        }
                        // sysDescr present: set match default if not present
                        if (! isset($state['sysDescr_match'])) {
                            if (isset($entry['sysDescr'])) {
                                $sd = $entry['sysDescr'];
                                $state['sysDescr_match'] = is_array($sd) ? (string) ($sd[0] ?? '') : (string) $sd;
                            } elseif (isset($entry['sysDescr_regex'])) {
                                // We won't prefill regex into substring prompt
                            }
                        }
                        // If method still not decided from snmpget, prefer sysObjectID, then sysDescr
                        if (! isset($state['method'])) {
                            if (isset($entry['sysObjectID']) || isset($entry['sysObjectID_regex'])) {
                                $state['method'] = 'sysObjectID';
                            } elseif (isset($entry['sysDescr']) || isset($entry['sysDescr_regex'])) {
                                $state['method'] = 'sysDescr';
                            }
                        }
                    }
                }

                // Apply simple preloads only if not already cached
                foreach ($preload as $k => $v) {
                    if ($v !== null && ! array_key_exists($k, $state)) {
                        $state[$k] = $v;
                    }
                }
                Cache::put($cacheKey, $state, now()->addDays(7));
            }

            if (! $this->option('overwrite')) {
                if (! $this->confirm('Continue creating a new definition anyway?', false)) {
                    return self::SUCCESS; // user decided to not create a new one
                }
            }
        }

        // 4) Defaults and prompts with override capability
        $derivedOs   = $this->deriveOsSlug($sysDescr);
        $derivedText = $this->deriveText($sysDescr);
        $derivedType = $this->inferType($sysObjectID, $sysDescr) ?: 'network';
        $derivedIcon = $this->deriveIcon($sysDescr) ?: $derivedOs;

        $nonInteractive = (bool) $this->option('non-interactive');

        // Respect explicit options first; otherwise prompt with suggested defaults in interactive mode
        $os = $this->option('os');
        if ($os === null) {
            $defaultOs = $state['os'] ?? ($derivedOs ?: '');
            $os = $nonInteractive ? ($state['os'] ?? ($derivedOs ?: null)) : $this->ask('OS slug (kebab-case)', $defaultOs);
        }
        if ($os !== null) {
            $state['os'] = $os;
            Cache::put($cacheKey, $state, now()->addDays(7));
        }

        $text = $this->option('text');
        if ($text === null) {
            $defaultText = $state['text'] ?? ($derivedText ?: '');
            $text = $nonInteractive ? ($state['text'] ?? ($derivedText ?: null)) : $this->ask('Display text', $defaultText);
        }
        if ($text !== null) {
            $state['text'] = $text;
            Cache::put($cacheKey, $state, now()->addDays(7));
        }

        $type = $this->option('type');
        if ($type === null) {
            $defaultType = $state['type'] ?? $derivedType;
            $type = $nonInteractive ? ($state['type'] ?? $derivedType) : $this->ask('Device type', $defaultType);
        }
        if ($type !== null) {
            $state['type'] = $type;
            Cache::put($cacheKey, $state, now()->addDays(7));
        }

        $icon = $this->option('icon');
        if ($icon === null) {
            $defaultIcon = $state['icon'] ?? (($derivedIcon ?: $os) ?: '');
            $icon = $nonInteractive ? ($state['icon'] ?? ($derivedIcon ?: $os)) : $this->ask('Icon name', $defaultIcon);
        }
        if ($icon !== null) {
            $state['icon'] = $icon;
            Cache::put($cacheKey, $state, now()->addDays(7));
        }

        $group = $this->option('group') ?? ($state['group'] ?? null);
        if ($group !== null) {
            $state['group'] = $group;
            Cache::put($cacheKey, $state, now()->addDays(7));
        }
        $mibDir = $this->option('mib-dir') ?? ($state['mib_dir'] ?? null);
        if ($mibDir !== null) {
            $state['mib_dir'] = $mibDir;
            Cache::put($cacheKey, $state, now()->addDays(7));
        }

        if (! $os || ! $text) {
            $this->error('Missing required values: os and text must be provided or derivable.');
            return self::FAILURE;
        }

        // 5) Build discovery (allow user to choose detection method)
        $methodOption = $this->option('method');
        $validMethods = ['sysObjectID', 'sysDescr', 'snmpget'];
        if ($methodOption !== null && ! in_array($methodOption, $validMethods, true)) {
            $this->error("Invalid --method '{$methodOption}'. Valid values: " . implode(', ', $validMethods));
            return self::FAILURE;
        }

        $method = null;
        if ($nonInteractive) {
            // In non-interactive mode, prefer explicit option, then cached method, then fallback to sysObjectID
            $method = $methodOption ?? ($state['method'] ?? 'sysObjectID');
        } else {
            if ($methodOption !== null) {
                // Respect explicit option if provided
                $method = $methodOption;
            } else {
                // Always prompt in interactive mode, using cached method as default if available
                $sysDescrShort = Str::limit($sysDescr, 80);
                $choices = [
                    "sysObjectID (Best) [{$sysObjectID}]",
                    "sysDescr [{$sysDescrShort}]",
                    'snmp get (worst/slowest)',
                ];
                $defaultIdx = 0;
                if (! empty($state['method'])) {
                    $defaultIdx = array_search($state['method'], ['sysObjectID','sysDescr','snmpget'], true);
                    $defaultIdx = $defaultIdx === false ? 0 : $defaultIdx;
                }
                $choice = $this->choice('Choose detection method', $choices, $defaultIdx);
                if (Str::startsWith($choice, 'sysObjectID')) {
                    $method = 'sysObjectID';
                } elseif (Str::startsWith($choice, 'sysDescr')) {
                    $method = 'sysDescr';
                } else {
                    $method = 'snmpget';
                }
            }
        }
        $state['method'] = $method;
        Cache::put($cacheKey, $state, now()->addDays(7));

        $discoveryEntry = [];
        if ($method === 'sysObjectID') {
            $suggestedPrefix = $this->suggestSysObjectIdPrefix($sysObjectID);
            $defaultPrefix = $state['sysObjectID_prefix'] ?? $suggestedPrefix;
            $prefix = $nonInteractive ? ($state['sysObjectID_prefix'] ?? $suggestedPrefix) : $this->ask('sysObjectID prefix', $defaultPrefix);
            // normalize prefix: ensure leading and trailing dot
            $prefix = Str::start((string) $prefix, '.');
            if ($prefix !== $sysObjectID && ! Str::endsWith($prefix, '.')) {
                $prefix .= '.';
            }
            $state['sysObjectID_prefix'] = $prefix;
            Cache::put($cacheKey, $state, now()->addDays(7));
            $discoveryEntry['sysObjectID'] = [$prefix];
        } elseif ($method === 'sysDescr') {
            if ($nonInteractive) {
                $this->error('Non-interactive sysDescr method selected, but no match provided. Use interactive mode or default to sysObjectID.');
                return self::FAILURE;
            }
            $defaultDescrMatch = $state['sysDescr_match'] ?? $this->suggestSysDescrMatch($sysDescr);
            $descrMatch = (string) $this->ask('sysDescr must contain (substring match)', $defaultDescrMatch);
            if (trim($descrMatch) === '') {
                $this->error('sysDescr match cannot be empty.');
                return self::FAILURE;
            }
            $state['sysDescr_match'] = $descrMatch;
            Cache::put($cacheKey, $state, now()->addDays(7));
            $discoveryEntry['sysDescr'] = $descrMatch;
        } else { // snmpget
            if ($nonInteractive) {
                $this->error('Non-interactive snmpget method selected, but no OID/value provided. Use interactive mode or default to sysObjectID.');
                return self::FAILURE;
            }
            $oidDefault = $state['snmpget_oid'] ?? '';
            $oid = (string) $this->ask('SNMP OID to query (numeric or textual, e.g., .1.3.6... or NET-SNMP-EXTEND-MIB::nsExtendOutput1Line."distro")', $oidDefault);
            if (trim($oid) === '') {
                $this->error('OID cannot be empty for snmpget detection.');
                return self::FAILURE;
            }
            // Persist the OID immediately so it is remembered even if later prompts fail/abort
            $state['snmpget_oid'] = $oid;
            Cache::put($cacheKey, $state, now()->addDays(7));
            // Try to fetch the value from the device to help the user
            $numeric = Str::startsWith($oid, '.');
            // If the OID is textual and no mib_dir is set, prompt the user to provide one to help resolve the symbol
            if (! $nonInteractive && ! $numeric) {
                $defaultMibDir = $state['mib_dir'] ?? '';
                $answer = (string) $this->ask('MIB directory to resolve textual OIDs (optional, e.g., mibs/vendor; leave empty to skip)', $defaultMibDir);
                $answer = trim($answer);
                if ($answer !== '') {
                    $mibDir = $answer;
                    $state['mib_dir'] = $mibDir;
                    Cache::put($cacheKey, $state, now()->addDays(7));
                }
            }

            $fetched = null;
            try {
                $query = SnmpQuery::make()->device($device);
                if ($mibDir) {
                    $query = $query->mibDir($mibDir);
                }
                if ($numeric) {
                    $query = $query->numeric(true);
                }
                $fetched = $query->get($oid)->value();
            } catch (\Throwable $e) {
                $fetched = null; // ignore fetch errors and continue
            }
            if ($fetched !== null && $fetched !== '') {
                $this->info('Fetched value for OID: ' . $fetched);
            } else {
                $this->warn('Could not fetch a value for the provided OID, please enter expected value manually.');
            }
            $ops = ['=', 'contains', 'starts', '!=', 'regex'];
            $defaultOpIdx = 0;
            if (! empty($state['snmpget_op'])) {
                $idx = array_search($state['snmpget_op'], $ops, true);
                if ($idx !== false) { $defaultOpIdx = $idx; }
            }
            $op = $this->choice('Comparison operator', $ops, $defaultOpIdx);
            $defaultValue = $state['snmpget_value'] ?? (string) ($fetched ?? '');
            $value = (string) $this->ask('Expected value (or regex pattern when using regex)', $defaultValue);
            if (trim($value) === '') {
                $this->error('Value cannot be empty for snmpget detection.');
                return self::FAILURE;
            }
            $state['snmpget_oid'] = $oid;
            $state['snmpget_op'] = $op;
            $state['snmpget_value'] = $value;
            Cache::put($cacheKey, $state, now()->addDays(7));
            $discoveryEntry['sysObjectID'] = $sysObjectID;
            $discoveryEntry['snmpget'] = [
                'oid' => $oid,
                'op' => $op,
                'value' => $value,
            ];
        }

        $detection_data = [
            'os' => $os,
            'text' => $text,
            'type' => $type,
            'icon' => $icon,
            'mib_dir' => $mibDir,
            'discovery' => [$discoveryEntry],
        ];
        if ($group !== null) {
            $detection_data['group'] = $group;
        }
        $yaml = $this->renderYaml($detection_data);

        // Save final preview to state for resume convenience
        $state['preview_yaml'] = $yaml;
        Cache::put($cacheKey, $state, now()->addDays(7));

        // 6) Preview
        $this->newLine();
        $this->line($yaml);
        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No files written.');
            Cache::forget($cacheKey); // clear session on dry-run completion
            $this->printNextSteps($os);
            return self::SUCCESS;
        }

        // Validation
        dump($detection_data);
        try {
            $schema = (object) ['$ref' => 'file://' . resource_path('definitions/schema/os_schema.json')];
            $validator = new \JsonSchema\Validator;
            $validator->validate(
                $detection_data,
                $schema,
                Constraint::CHECK_MODE_TYPE_CAST | Constraint::CHECK_MODE_VALIDATE_SCHEMA | Constraint::CHECK_MODE_EXCEPTIONS
            );
        } catch (JsonDecodingException|ValidationException $e) {
            $error = $e->getMessage();
            if (str_contains($error, 'Error validating /discovery/')) {
                $error = 'Discovery must contain an identifier sysObjectID or sysDescr';
            }
            $this->error("Invalid YAML: $error");
            return self::FAILURE;
        }
        LibrenmsConfig::set("os.$os", $detection_data);
        $detected_os = Core::detectOS($device, false);
        if ($os !== $detected_os) {
            $this->error("Detected OS '$detected_os' does not match expected '$os'");
            return self::FAILURE;
        }

        // 7) Write file
        $path = base_path("resources/definitions/os_detection/{$os}.yaml");
        if (file_exists($path) && ! $this->option('overwrite')) {
            $this->error("{$os}.yaml already exists. Use --overwrite to overwrite.");
            return self::FAILURE;
        }
        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $yaml);

        // 8) Optional test data (print guidance for minimal change)
        if (! $this->option('no-testdata')) {
            $this->line("Tip: capture test data with:\n  ./scripts/collect-snmp-data.php -h {$device->device_id} -v ''\n  ./scripts/save-test-data.php -o {$os}");
        }

        $this->info("OS detection created: resources/definitions/os_detection/{$os}.yaml");
        $this->printNextSteps($os);

        // Clear cached state on success
        Cache::forget($cacheKey);

        return self::SUCCESS;
    }

    private function resolveDevice(string $spec): ?\App\Models\Device
    {
        // Use the helper similar to SnmpFetch
        $ids = Device::whereDeviceSpec($spec)->pluck('device_id');
        $id = $ids->first();
        return $id ? DeviceCache::get($id) : null;
    }

    /**
     * Use existing detection logic to find matching OS definitions.
     *
     * @return array{0: array<string>, 1: array<string,string>} [matches, reasons]
     */
    private function findMatchingOsDefinitions(string $sysObjectID, string $sysDescr): array
    {
        $matches = [];
        $reasons = [];

        // Resolve device to perform any necessary snmpget/snmpwalk checks
        $device = $this->resolveDevice((string) $this->option('device'));
        if (! $device) {
            return [$matches, $reasons];
        }

        // Ensure the device has the identifiers we just fetched
        $device->sysObjectID = $sysObjectID;
        $device->sysDescr = $sysDescr;

        // Load already-parsed OS definitions from config
        $os_defs = LibrenmsConfig::get('os');
        if (! is_array($os_defs)) {
            return [$matches, $reasons];
        }

        // Use the existing detection function to find the best matching OS
        $detected = Core::detectOS($device, false);
        // Treat linux/freebsd/airos as generic-like: don't block creating a more specific new OS
        $genericLike = ['generic', 'linux', 'freebsd', 'airos'];
        if ($detected && ! in_array($detected, $genericLike, true)) {
            $matches[$detected] = strlen($sysObjectID);
            $reasons[$detected] = 'Matched via Core::detectOS() using repository OS definitions';
        }

        if (! empty($matches)) {
            arsort($matches);
        }

        return [array_keys($matches), $reasons];
    }

    private function deriveOsSlug(string $sysDescr): ?string
    {
        // naive vendor-token derivation: take first word, lowercase, kebab-case
        $vendor = trim(strtok($sysDescr, ' ,;()')); // until space or punctuation
        $vendor = Str::of($vendor)->ascii()->lower()->kebab()->trim('-');
        return $vendor ? (string) $vendor : null;
    }

    private function deriveText(string $sysDescr): ?string
    {
        $vendor = trim(strtok($sysDescr, ' ,;()'));
        if (! $vendor) {
            return null;
        }
        // Pick a generic product class word if present
        $class = Str::contains(Str::lower($sysDescr), 'ups') ? 'UPS' : (Str::contains(Str::lower($sysDescr), 'router') ? 'Router' : 'Switch');
        return Str::headline($vendor) . ' ' . $class;
    }

    private function inferType(string $sysObjectID, string $sysDescr): ?string
    {
        $s = Str::lower($sysDescr);
        if (Str::contains($s, 'ups') || Str::contains($s, 'power')) {
            return 'power';
        }
        if (Str::contains($s, ['server', 'linux', 'windows'])) {
            return 'server';
        }
        return 'network';
    }

    private function deriveIcon(string $sysDescr): ?string
    {
        $vendor = trim(strtok($sysDescr, ' ,;()'));
        return $vendor ? Str::of($vendor)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-') : null;
    }

    private function suggestSysObjectIdPrefix(string $fullOid): string
    {
        // Ensure leading dot and trailing dot, then shorten by one arc to a family prefix
        $oid = Str::start($fullOid, '.');
        $parts = array_values(array_filter(explode('.', $oid), fn($p) => $p !== ''));
        // drop last arc to get the family, ensure trailing dot
        if (count($parts) > 1) {
            array_pop($parts);
        }
        return '.' . implode('.', $parts) . '.';
    }

    private function suggestSysDescrMatch(string $sysDescr): string
    {
        // Heuristic: return the first one or two words (letters/numbers) from sysDescr
        if (preg_match('/[A-Za-z][A-Za-z0-9+._-]*(?:\s+[A-Za-z][A-Za-z0-9+._-]*)?/', $sysDescr, $m)) {
            return $m[0];
        }
        $line = trim(strtok($sysDescr, "\n"));
        return Str::limit($line !== '' ? $line : $sysDescr, 20, '');
    }

    private function renderYaml(array $data): string
    {
        $keyOrder = ['os', 'text', 'group', 'type', 'icon', 'mib_dir', 'discovery'];
        $optionalKeys = ['group', 'icon', 'mib_dir'];

        $ordered = collect($keyOrder)
            ->filter(fn($key) => array_key_exists($key, $data))
            ->reject(fn($key) => in_array($key, $optionalKeys) && blank($data[$key]))
            ->mapWithKeys(fn($key) => [$key => $data[$key]])
            ->toArray();

        return Str::finish(Yaml::dump($ordered, 10, 4), "\n");
    }

    private function printNextSteps(string $os): void
    {
        $this->info("Next: run 'lnms dev:check' and 'lnms dev:check unit --db --snmpsim'.");
        $this->line("You can extend detection or add discovery under resources/definitions/os_discovery/{$os}.yaml later.");
    }
}

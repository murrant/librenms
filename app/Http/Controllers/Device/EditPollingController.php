<?php

namespace App\Http\Controllers\Device;

use App\Actions\Device\DeviceIsSnmpable;
use App\Data\Polling\Methods\Icmp;
use App\Data\Polling\Methods\Ipmi;
use App\Data\Polling\Methods\Snmp;
use App\Data\Polling\Methods\UnixAgent;
use App\Data\Secrets\SecretData;
use App\Facades\LibrenmsConfig;
use App\Http\Interfaces\ToastInterface;
use App\Models\Device;
use App\Models\Secret;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use LibreNMS\Enum\PortAssociationMode;
use LibreNMS\Enum\SecretType;
use LibreNMS\Interfaces\PollingMethod;

class EditPollingController
{
    use AuthorizesRequests;

    /**
     * @return array<string, array{class: class-string<PollingMethod>, secret_type: ?SecretType}>
     */
    private function methodConfigs(): array
    {
        return [
            'icmp' => ['class' => Icmp::class, 'secret_type' => null],
            'ipmi' => ['class' => Ipmi::class, 'secret_type' => null],
            'snmp' => ['class' => Snmp::class, 'secret_type' => SecretType::Snmp],
            'unix-agent' => ['class' => UnixAgent::class, 'secret_type' => null],
        ];
    }

    /**
     * @return array{class: class-string<PollingMethod>, secret_type: ?SecretType}
     */
    private function methodConfig(string $methodType): array
    {
        return $this->methodConfigs()[$methodType] ?? abort(404);
    }

    /**
     * @throws AuthorizationException
     */
    public function index(Device $device): View
    {
        $this->authorize('viewAny', Secret::class);
        $this->authorize('update', $device);

        $device->load('secrets');

        $allMethods = collect($this->methodConfigs())->map(function (array $methodConfig, string $methodType) use ($device): array {
            $pollingMethod = app($methodConfig['class']);
            $secretType = $methodConfig['secret_type'];
            $secret = $secretType ? $device->secrets->first(fn (Secret $secret): bool => $secret->secret_type === $secretType) : null;
            $schema = $secretType ? $secretType->secretClass()::getUiSchema() : [];
            $schemaFields = collect($schema)->map(function (array $field, string $key): array {
                $visibleIfExpression = null;

                if (isset($field['visible_if']) && is_array($field['visible_if'])) {
                    $visibleIfExpression = collect($field['visible_if'])->map(function (mixed $condVal, string $condKey): string {
                        if (is_array($condVal) && isset($condVal['$in']) && is_array($condVal['$in'])) {
                            return json_encode(array_values($condVal['$in'])) . '.includes(formData[' . json_encode($condKey) . '])';
                        }

                        return 'formData[' . json_encode($condKey) . '] === ' . json_encode((string) $condVal);
                    })->implode(' && ');
                }

                return [
                    ...$field,
                    'key' => $key,
                    'field_type' => $field['type'] ?? 'text',
                    'visible_if_expression' => $visibleIfExpression,
                ];
            })->values()->all();

            return [
                'type' => $methodType,
                'label' => strtoupper($methodType),
                'schema' => $schema,
                'schema_defaults' => collect($schema)->mapWithKeys(
                    fn (array $field, string $key): array => [
                        $key => $field['default'] ?? (isset($field['options']) ? array_key_first($field['options']) : ''),
                    ]
                )->all(),
                'schema_fields' => $schemaFields,
                'device_settings' => $this->formatDeviceSettings($device, $pollingMethod->getDeviceSettings()),
                'secret' => $secret,
                'secret_form_data' => collect($schema)->mapWithKeys(
                    fn (array $field, string $key): array => [(string) $key => (string) data_get($secret?->data, $key, '')]
                )->all(),
                'usage_count' => $secret ? $secret->devices()->count() : 0,
                'enabled' => $pollingMethod->isEnabled($device),
                'configured' => $pollingMethod->isConfigured($device, $secret),
                'last_check_successful' => $pollingMethod->lastCheckSuccessful($device, $secret),
            ];
        });

        $configuredMethods = $allMethods->filter(fn (array $method): bool => $method['configured'])->values();
        $unconfiguredMethods = $allMethods->filter(fn (array $method): bool => ! $method['configured'])->values();

        return view('device.edit.polling', [
            'device' => $device,
            'configuredMethods' => $configuredMethods,
            'unconfiguredMethods' => $unconfiguredMethods,
            'availableSecrets' => Secret::query()->orderBy('description')->get()->groupBy(
                fn (Secret $secret): string => $secret->secret_type->value
            ),
        ]);
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function store(Request $request, Device $device, ToastInterface $toast): RedirectResponse
    {
        $this->authorize('create', Secret::class);
        $this->authorize('update', $device);

        $validated = $request->validate([
            'method_type' => ['required', Rule::in(array_keys($this->methodConfigs()))],
            'credential_mode' => ['nullable', Rule::in(['existing', 'new'])],
            'secret_id' => ['nullable', 'integer', 'exists:secrets,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'default' => ['nullable', 'boolean'],
        ]);

        $methodType = $validated['method_type'];
        $methodConfig = $this->methodConfig($methodType);
        $pollingMethod = app($methodConfig['class']);
        $secretType = $methodConfig['secret_type'];

        if ($methodType === 'unix-agent') {
            if ($device->hasAttrib('override_Unixagent_port')) {
                 throw ValidationException::withMessages([
                    'method_type' => __('This polling method is already configured for this device.'),
                ]);
            }
            $device->setAttrib('override_Unixagent_port', 6556);
            $toast->success(__('Polling method added'));
            return redirect()->route('device.edit.polling', $device);
        }

        if ($methodType === 'ipmi') {
            if ($pollingMethod->isConfigured($device, null)) {
                throw ValidationException::withMessages([
                    'method_type' => __('This polling method is already configured for this device.'),
                ]);
            }

            $toast->success(__('Polling method added'));

            return redirect()->route('device.edit.polling', $device);
        }

        if (! $secretType) {
            throw ValidationException::withMessages([
                'method_type' => __('This polling method cannot be configured with a secret.'),
            ]);
        }

        if ($device->secrets()->wherePivot('secret_type', $secretType->value)->exists()) {
            throw ValidationException::withMessages([
                'method_type' => __('This polling method is already configured for this device.'),
            ]);
        }

        $secret = ($validated['credential_mode'] ?? 'existing') === 'existing'
            ? $this->resolveExistingSecret($validated['secret_id'] ?? null, $secretType)
            : $this->createSecret($request, $secretType, $validated);

        $device->secrets()->attach($secret->id, ['secret_type' => $secretType->value]);

        $toast->success(__('Polling method added'));

        return redirect()->route('device.edit.polling', $device);
    }

    /**
     * @throws AuthorizationException
     */
    public function update(Request $request, Device $device, string $methodType, ToastInterface $toast): RedirectResponse
    {
        $this->authorize('update', Secret::class);
        $this->authorize('update', $device);

        $methodConfig = $this->methodConfig($methodType);
        $pollingMethod = app($methodConfig['class']);
        $secretType = $methodConfig['secret_type'];
        $deviceSettingsRules = collect($pollingMethod->getDeviceSettings())
            ->mapWithKeys(fn (array $setting): array => [$setting['name'] => $setting['rules'] ?? ['nullable']])
            ->all();

        $validated = $request->validate([
            'secret_id' => ['nullable', 'integer', 'exists:secrets,id'],
            'disabled' => ['nullable', 'boolean'],
            'force_save' => ['nullable', 'boolean'],
            'sysName' => ['nullable', 'string'],
            'hardware' => ['nullable', 'string'],
            'os' => ['nullable', 'string'],
            'port_association_mode' => ['nullable', 'integer'],
            'secret_update_mode' => ['nullable', 'string', 'in:update,create'],
            ...$deviceSettingsRules,
        ]);

        if ($secretType && $request->has('secret_data')) {
            $secretClass = $secretType->secretClass();
            $secretRules = [];
            foreach ($secretClass::rules() as $key => $rule) {
                $secretRules["secret_data.{$key}"] = $rule;
            }
            $validatedSecretData = $request->validate($secretRules)['secret_data'] ?? [];

            $secretUpdateMode = $validated['secret_update_mode'] ?? 'update';
            $existingSecret = $device->secrets()->wherePivot('secret_type', $secretType->value)->first();

            if ($existingSecret) {
                if ($secretUpdateMode === 'create') {
                    $newSecret = Secret::query()->create([
                        'description' => 'Custom ' . strtoupper($methodType) . ' for ' . $device->hostname,
                        'secret_type' => $secretType,
                        'default' => false,
                        'data' => $validatedSecretData,
                    ]);
                    $device->secrets()->detach($existingSecret->id);
                    $device->secrets()->attach($newSecret->id, ['secret_type' => $secretType->value]);
                } else {
                    $existingSecret->update(['data' => $validatedSecretData]);
                }
            }
        } elseif ($secretType && Arr::has($validated, 'secret_id')) {
            $secret = $this->resolveExistingSecret((int) $validated['secret_id'], $secretType);
            $device->secrets()->updateExistingPivot($secret->id, ['secret_type' => $secretType->value]);
        }

        if ($methodType === 'icmp') {
            $icmpDisabled = (bool) ($validated['disabled'] ?? false);
            if ($icmpDisabled) {
                $device->status_reason = 'icmp';
                $device->status = false;
            } elseif ($device->status_reason === 'icmp') {
                $device->status_reason = '';
            }
            $device->save();
            $toast->success(__('Polling method updated'));
        } elseif ($methodType === 'unix-agent') {
            $unixAgentDisabled = (bool) ($validated['disabled'] ?? false);

            if (! $unixAgentDisabled) {
                $this->saveDeviceSettings($device, $pollingMethod->getDeviceSettings(), $validated);
            }

            $toast->success(__('Polling method updated'));
        } elseif ($methodType === 'ipmi') {
            $ipmiDisabled = (bool) ($validated['disabled'] ?? false);

            if (! $ipmiDisabled) {
                $this->saveDeviceSettings($device, $pollingMethod->getDeviceSettings(), $validated);
                $toast->success(__('Polling method updated'));
            }
        } elseif ($methodType === 'snmp') {
            $snmpDisabled = (bool) ($validated['disabled'] ?? false);
            $device->snmp_disable = $snmpDisabled;

            if ($snmpDisabled) {
                $device->features = null;
                $device->hardware = $validated['hardware'] ?? null;
                $device->icon = null;
                $device->os = !empty($validated['os']) ? strip_tags($validated['os']) : 'ping';
                $device->sysName = $validated['sysName'] ?? null;
                $device->version = null;
                $device->save();

                $toast->success(__('SNMP polling disabled'));
            } else {
                $this->saveDeviceSettings($device, $pollingMethod->getDeviceSettings(), $validated);
                $device->port = $validated['port'] ?: LibrenmsConfig::get('snmp.port');
                $device->transport = $validated['transport'] ?: 'udp';
                $device->port_association_mode = $validated['port_association_mode'] ?? PortAssociationMode::ifIndex;
                $device->retries = $validated['retries'] ?: null;
                $device->timeout = $validated['timeout'] ?: null;

                $forceSave = (bool) ($validated['force_save'] ?? false);
                $deviceIsSnmpable = false;

                if (!$forceSave) {
                    $deviceIsSnmpable = app(DeviceIsSnmpable::class)->execute($device);
                }

                if ($forceSave || $deviceIsSnmpable) {
                    $device->save();
                    $toast->success(__('Polling method updated'));
                } else {
                    $toast->error(__('Could not connect to :device with those SNMP settings. To save anyway, click Force Save.', ['device' => $device->hostname]));
                }
            }
        } else {
            $toast->success(__('Polling method updated'));
        }

        return redirect()->route('device.edit.polling', $device);
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Device $device, string $methodType, ToastInterface $toast): RedirectResponse
    {
        $this->authorize('delete', Secret::class);
        $this->authorize('update', $device);

        $methodConfig = $this->methodConfig($methodType);
        $secretType = $methodConfig['secret_type'];
        $pollingMethod = app($methodConfig['class']);

        if ($methodType === 'unix-agent') {
             $device->forgetAttrib('override_Unixagent_port');
             $toast->success(__('Polling method removed'));
             return redirect()->route('device.edit.polling', $device);
        }

        if ($methodType === 'ipmi') {
            foreach ($pollingMethod->getDeviceSettings() as $setting) {
                if (($setting['storage'] ?? 'field') === 'attrib') {
                    $device->forgetAttrib($setting['key']);
                }
            }

            $toast->success(__('Polling method removed'));

            return redirect()->route('device.edit.polling', $device);
        }

        $secret = $secretType ? $device->secrets()->wherePivot('secret_type', $secretType->value)->first() : null;

        if ($secret) {
            $device->secrets()->detach($secret->id);
        }

        if ($methodType === 'snmp') {
            $device->snmp_disable = true;
            $device->save();
        }

        $toast->success(__('Polling method removed'));

        return redirect()->route('device.edit.polling', $device);
    }

    private function resolveExistingSecret(?int $secretId, SecretType $type): Secret
    {
        if (! $secretId) {
            throw ValidationException::withMessages([
                'secret_id' => __('Select an existing credential.'),
            ]);
        }

        $secret = Secret::query()->findOrFail($secretId);
        if ($secret->secret_type !== $type) {
            throw ValidationException::withMessages([
                'secret_id' => __('Selected credential does not match polling method type.'),
            ]);
        }

        return $secret;
    }

    private function createSecret(Request $request, SecretType $type, array $validated): Secret
    {
        /** @var class-string<SecretData> $class */
        $class = $type->secretClass();
        $data = $request->validate($class::rules());

        return Secret::query()->create([
            'description' => $validated['description'] ?: strtoupper($type->value) . ' ' . $request->user()?->user_id,
            'secret_type' => $type,
            'default' => (bool) ($validated['default'] ?? false),
            'data' => $data,
        ]);
    }


    private function formatDeviceSettings(Device $device, array $settings): array
    {
        return collect($settings)->map(function (array $setting) use ($device): array {
            $value = match ($setting['storage'] ?? 'field') {
                'attrib' => $device->getAttrib($setting['key'], $setting['default'] ?? null),
                default => $device->{$setting['key']} ?? ($setting['default'] ?? null),
            };

            return [
                ...$setting,
                'value' => $value,
            ];
        })->all();
    }

    private function saveDeviceSettings(Device $device, array $settings, array $validated): void
    {
        foreach ($settings as $setting) {
            $name = $setting['name'];
            if (! Arr::has($validated, $name)) {
                continue;
            }

            $value = $validated[$name];

            if ($value === null || $value === '') {
                $value = $setting['default'] ?? null;
            }

            if (($setting['storage']) === 'attrib') {
                if ($value === null || $value === '') {
                    $device->forgetAttrib($setting['key']);
                } else {
                    $device->setAttrib($setting['key'], $value);
                }
            } else {
                $device->{$setting['key']} = $value;
            }
        }
    }

}

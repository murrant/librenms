<?php

namespace App\Http\Controllers\Device;

use App\Actions\Device\DeviceIsSnmpable;
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

class EditPollingController
{
    use AuthorizesRequests;

    /**
     * @throws AuthorizationException
     */
    public function index(Device $device): View
    {
        $this->authorize('viewAny', Secret::class);
        $this->authorize('update', $device);

        $device->load('secrets');

        $allMethods = collect(SecretType::cases())->map(function (SecretType $type) use ($device): array {
            $secret = $device->secrets->first(fn (Secret $secret): bool => $secret->secret_type === $type);

            return [
                'type' => $type,
                'label' => strtoupper($type->value),
                'schema' => $type->secretClass()::getUiSchema(),
                'secret' => $secret,
                'usage_count' => $secret ? $secret->devices()->count() : 0,
                'enabled' => $this->isMethodEnabled($device, $type),
                'last_check_successful' => $this->lastCheckSuccessful($device, $type, $secret),
            ];
        });

        $configuredMethods = $allMethods->filter(fn ($m) => $m['secret'] !== null || $m['type'] === SecretType::Icmp)->values();
        $unconfiguredMethods = $allMethods->filter(fn ($m) => $m['secret'] === null && $m['type'] !== SecretType::Icmp)->values();

        return view('device.edit.polling', [
            'device' => $device,
            'configuredMethods' => $configuredMethods,
            'unconfiguredMethods' => $unconfiguredMethods,
            'availableSecrets' => Secret::query()->orderBy('description')->get()->groupBy(
                fn (Secret $secret): string => $secret->secret_type->value
            ),
            'pollerGroups' => LibrenmsConfig::get('distributed_poller') ? \App\Models\PollerGroup::select(['id', 'group_name'])->get() : collect(),
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
            'method_type' => ['required', Rule::in(array_map(fn (SecretType $type): string => $type->value, SecretType::cases()))],
            'credential_mode' => ['required', Rule::in(['existing', 'new'])],
            'secret_id' => ['nullable', 'integer', 'exists:secrets,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'default' => ['nullable', 'boolean'],
        ]);

        $type = SecretType::from($validated['method_type']);

        if ($device->secrets()->wherePivot('secret_type', $type->value)->exists()) {
            throw ValidationException::withMessages([
                'method_type' => __('This polling method is already configured for this device.'),
            ]);
        }

        $secret = $validated['credential_mode'] === 'existing'
            ? $this->resolveExistingSecret($validated['secret_id'] ?? null, $type)
            : $this->createSecret($request, $type, $validated);

        $device->secrets()->attach($secret->id, ['secret_type' => $type->value]);

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

        $type = SecretType::tryFrom($methodType) ?? abort(404);

        $validated = $request->validate([
            'secret_id' => ['nullable', 'integer', 'exists:secrets,id'],
            'disabled' => ['nullable', 'boolean'],
            'force_save' => ['nullable', 'boolean'],
            'sysName' => ['nullable', 'string'],
            'hardware' => ['nullable', 'string'],
            'os' => ['nullable', 'string'],
            'port' => ['nullable', 'integer'],
            'transport' => ['nullable', 'string'],
            'timeout' => ['nullable', 'integer'],
            'retries' => ['nullable', 'integer'],
            'port_association_mode' => ['nullable', 'integer'],
            'max_repeaters' => ['nullable', 'integer'],
            'max_oid' => ['nullable', 'integer'],
            'poller_group' => ['nullable', 'integer'],
            'ipmi_hostname' => ['nullable', 'string'],
            'ipmi_port' => ['nullable', 'integer'],
            'ipmi_ciphersuite' => ['nullable', 'string'],
            'ipmi_timeout' => ['nullable', 'integer'],
            'secret_update_mode' => ['nullable', 'string', 'in:update,create'],
        ]);

        if ($request->has('secret_data')) {
            $secretClass = $type->secretClass();
            $secretRules = [];
            foreach ($secretClass::rules() as $key => $rule) {
                $secretRules["secret_data.{$key}"] = $rule;
            }
            $validatedSecretData = $request->validate($secretRules)['secret_data'] ?? [];

            $secretUpdateMode = $validated['secret_update_mode'] ?? 'update';
            $existingSecret = $device->secrets()->wherePivot('secret_type', $type->value)->first();

            if ($existingSecret) {
                if ($secretUpdateMode === 'create') {
                    $newSecret = Secret::query()->create([
                        'description' => "Custom " . strtoupper($type->value) . " for " . $device->hostname,
                        'secret_type' => $type,
                        'default' => false,
                        'data' => $validatedSecretData,
                    ]);
                    $device->secrets()->detach($existingSecret->id);
                    $device->secrets()->attach($newSecret->id, ['secret_type' => $type->value]);
                } else {
                    $existingSecret->update(['data' => $validatedSecretData]);
                }
            }
        } elseif (Arr::has($validated, 'secret_id')) {
            $secret = $this->resolveExistingSecret((int) $validated['secret_id'], $type);
            $device->secrets()->updateExistingPivot($secret->id, ['secret_type' => $type->value]);
        }

        if ($type === SecretType::Icmp) {
            $icmpDisabled = (bool) ($validated['disabled'] ?? false);
            if ($icmpDisabled) {
                $device->status_reason = 'icmp';
                $device->status = false;
            } elseif ($device->status_reason === 'icmp') {
                $device->status_reason = '';
            }
            $device->save();
            $toast->success(__('Polling method updated'));
        } elseif ($type === SecretType::Ipmi) {
            $ipmiDisabled = (bool) ($validated['disabled'] ?? false);

            if ($ipmiDisabled) {
                // Let the disable be logical for now
            } else {
                foreach (['ipmi_hostname', 'ipmi_port', 'ipmi_ciphersuite', 'ipmi_timeout'] as $attrib) {
                    if (!empty($validated[$attrib])) {
                        $device->setAttrib($attrib, $validated[$attrib]);
                    } else {
                        $device->forgetAttrib($attrib);
                    }
                }

                if (empty($validated['ipmi_port'])) {
                    $device->setAttrib('ipmi_port', 623);
                }

                $toast->success(__('Polling method updated'));
            }
        } elseif ($type === SecretType::Snmp) {
            $snmpDisabled = (bool) ($validated['disabled'] ?? false);
            $device->snmp_disable = $snmpDisabled;
            $device->poller_group = (int) ($validated['poller_group'] ?? 0);

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
                $device->port = $validated['port'] ?: LibrenmsConfig::get('snmp.port');
                $device->transport = $validated['transport'] ?: 'udp';
                $device->port_association_mode = $validated['port_association_mode'] ?? PortAssociationMode::IfIndex->value;
                $device->retries = $validated['retries'] ?: null;
                $device->timeout = $validated['timeout'] ?: null;

                $forceSave = (bool) ($validated['force_save'] ?? false);
                $deviceIsSnmpable = false;

                if (!$forceSave) {
                    $deviceIsSnmpable = app(DeviceIsSnmpable::class)->execute($device);
                }

                if ($forceSave || $deviceIsSnmpable) {
                    $device->save();

                    foreach (['max_repeaters' => 'snmp_max_repeaters', 'max_oid' => 'snmp_max_oid'] as $key => $attrib) {
                        if (!empty($validated[$key])) {
                            $device->setAttrib($attrib, $validated[$key]);
                        } else {
                            $device->forgetAttrib($attrib);
                        }
                    }
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

        $type = SecretType::tryFrom($methodType) ?? abort(404);
        $secret = $device->secrets()->wherePivot('secret_type', $type->value)->first();

        if ($secret) {
            $device->secrets()->detach($secret->id);
        }

        if ($type === SecretType::Snmp) {
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

    private function isMethodEnabled(Device $device, SecretType $type): bool
    {
        return match ($type) {
            SecretType::Snmp => ! $device->snmp_disable,
            SecretType::Icmp => $device->status_reason !== 'icmp',
            default => true,
        };
    }

    private function lastCheckSuccessful(Device $device, SecretType $type, ?Secret $secret): ?bool
    {
        if ($type === SecretType::Icmp) {
            return $device->last_ping_timetaken !== null;
        }

        if (! $secret) {
            return null;
        }

        return match ($type) {
            SecretType::Snmp => $device->last_polled !== null && ! $device->snmp_disable,
            default => null,
        };
    }
}

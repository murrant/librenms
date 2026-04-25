<?php

namespace App\Http\Controllers\Device;

use App\Data\Secrets\SecretData;
use App\Http\Interfaces\ToastInterface;
use App\Models\Device;
use App\Models\DevicePollingMethod;
use App\Models\Secret;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use LibreNMS\Enum\PollingMethodType;

class EditPollingController
{
    use AuthorizesRequests;

    /**
     * @throws AuthorizationException
     */
    public function index(Device $device): View
    {
        $this->authorize('update', $device);

        $device->load('pollingMethods.secret');

        $allMethods = collect(PollingMethodType::cases())->map(
            fn (PollingMethodType $type): array => $this->buildMethodData($device, $type)
        );

        return view('device.edit.polling', [
            'device' => $device,
            'configuredMethods' => $allMethods->filter(fn (array $m): bool => $m['configured'])->values(),
            'unconfiguredMethods' => $allMethods->filter(fn (array $m): bool => ! $m['configured'])->values(),
            'availableSecrets' => Secret::query()->orderBy('description')->get()->groupBy(
                fn (Secret $s): string => $s->secret_type->value
            ),
        ]);
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function store(Request $request, Device $device, ToastInterface $toast): RedirectResponse
    {
        $this->authorize('update', $device);

        $validated = $request->validate([
            'method_type' => ['required', Rule::enum(PollingMethodType::class)],
            'credential_mode' => ['nullable', Rule::in(['existing', 'new'])],
            'secret_id' => ['nullable', 'integer', 'exists:secrets,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'default' => ['nullable', 'boolean'],
        ]);

        $type = PollingMethodType::from($validated['method_type']);
        $pollingMethod = app($type->methodClass());

        if ($device->pollingMethods()->where('method_type', $type->value)->exists()) {
            throw ValidationException::withMessages([
                'method_type' => __('poller.method_exists'),
            ]);
        }

        $validatedSettings = $request->validate([
            'settings' => ['nullable', 'array'],
            ...collect($pollingMethod->getRules())
                ->mapWithKeys(fn (array|string $rule, string $key): array => ["settings.$key" => $rule])
                ->all(),
        ])['settings'] ?? [];

        $secret = null;
        if ($type->hasSecret()) {
            $this->authorize('create', Secret::class);
            $secret = ($validated['credential_mode'] ?? 'existing') === 'existing'
                ? $this->resolveExistingSecret($validated['secret_id'] ?? null, $type)
                : $this->createSecret($request, $type, $validated);
        }

        $row = new DevicePollingMethod([
            'device_id' => $device->device_id,
            'method_type' => $type,
            'enabled' => true,
            'affects_availability' => (bool) ($pollingMethod->getDefaults()['affects_availability'] ?? false),
            'secret_id' => $secret?->id,
            'settings' => [],
        ]);

        $row->setRelation('device', $device);

        $schemaDefaults = collect($pollingMethod->getSettingsSchema())
            ->mapWithKeys(fn (array $field, string $key): array => [
                $key => $field['default'] ?? (isset($field['options']) ? array_key_first($field['options']) : null),
            ])
            ->reject(fn (mixed $value): bool => $value === null)
            ->all();

        $row->settings = array_merge(
            $schemaDefaults,
            collect($pollingMethod->getDefaults())->except('affects_availability')->all(),
            $validatedSettings
        );
        $device->pollingMethods()->save($row);

        $toast->success(__('poller.method_added'));

        return redirect()->route('device.edit.polling', $device);
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(Request $request, Device $device, string $methodType, ToastInterface $toast): RedirectResponse
    {
        $this->authorize('update', $device);

        $type = PollingMethodType::tryFrom($methodType) ?? abort(404);
        $pollingMethod = app($type->methodClass());
        $row = $device->pollingMethods()->where('method_type', $type->value)->firstOrFail();

        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'affects_availability' => ['nullable', 'boolean'],
            'secret_update_mode' => ['nullable', 'string', 'in:update,create'],
            'secret_id' => ['nullable', 'integer', 'exists:secrets,id'],
            'force_save' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
            ...collect($pollingMethod->getRules())
                ->mapWithKeys(fn (array|string $rule, string $key): array => ["settings.$key" => $rule])
                ->all(),
        ]);

        $row->enabled = (bool) ($validated['enabled'] ?? true);
        $row->affects_availability = (bool) ($validated['affects_availability'] ?? false);
        $row->settings = array_merge(
            $row->settings ?? [],
            collect($pollingMethod->getSettingsSchema())
                ->keys()
                ->filter(fn (string $key): bool => array_key_exists($key, $validated['settings'] ?? []))
                ->mapWithKeys(fn (string $key): array => [$key => $validated['settings'][$key]])
                ->all()
        );

        if ($type->hasSecret() && $request->has('secret_data')) {
            $this->authorize('update', Secret::class);
            $row->secret_id = $this->updateSecret($request, $row, $type, $validated)->id;
        } elseif ($type->hasSecret() && array_key_exists('secret_id', $validated)) {
            $this->authorize('update', Secret::class);
            $row->secret_id = $this->resolveExistingSecret((int) $validated['secret_id'], $type)->id;
        }

        $row->save();

        // Sync device status fields if enabled state changed
        if ($row->wasChanged('enabled')) {
            $row->setRelation('device', $device);
            $row->syncDeviceStatus();
        }

        $toast->success(__('poller.method_updated'));

        return redirect()->route('device.edit.polling', $device);
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Device $device, string $methodType, ToastInterface $toast): RedirectResponse
    {
        $this->authorize('update', $device);

        $type = PollingMethodType::tryFrom($methodType) ?? abort(404);
        $row = $device->pollingMethods()->where('method_type', $type->value)->firstOrFail();

        if ($type->hasSecret()) {
            $this->authorize('delete', Secret::class);
        }

        $row->delete();

        $toast->success(__('poller.method_removed'));

        return redirect()->route('device.edit.polling', $device);
    }

    // ---- Private helpers ----

    private function buildMethodData(Device $device, PollingMethodType $type): array
    {
        $pollingMethod = app($type->methodClass());
        $row = $device->pollingMethods->firstWhere('method_type', $type);
        $secret = $row?->secret;
        $schema = $type->hasSecret() ? $type->secretClass()::getUiSchema() : [];
        $schemaFields = $this->buildSchemaFields($schema);
        $settingsSchema = $pollingMethod->getSettingsSchema();

        return [
            'type' => $type->value,
            'label' => __('poller.methods.' . $type->value),
            'schema_fields' => $schemaFields,
            'schema_defaults' => collect($schema)->mapWithKeys(
                fn (array $field, string $key): array => [
                    $key => $field['default'] ?? (isset($field['options']) ? array_key_first($field['options']) : ''),
                ]
            )->all(),
            'settings_fields' => $this->buildSchemaFields($settingsSchema, 'settingsData'),
            'settings' => $row?->settings ?? [],
            'affects_availability' => $row?->affects_availability ?? (bool) ($pollingMethod->getDefaults()['affects_availability'] ?? false),
            'secret' => $secret,
            'secret_form_data' => collect($schema)->mapWithKeys(
                fn (array $field, string $key): array => [
                    $key => (string) data_get($secret?->data, $key, ''),
                ]
            )->all(),
            'usage_count' => $secret?->devices()->count() ?? 0,
            'configured' => $row !== null,
            'enabled' => $row?->enabled ?? false,
            'last_check_successful' => $row?->last_check_successful,
        ];
    }

    private function buildSchemaFields(array $schema, string $dataVar = 'formData'): array
    {
        return collect($schema)->map(function (array $field, string $key) use ($dataVar): array {
            $visibleIfExpression = null;

            if (isset($field['visible_if']) && is_array($field['visible_if'])) {
                $visibleIfExpression = collect($field['visible_if'])
                    ->map(function (mixed $condVal, string $condKey): string {
                        if (is_array($condVal) && isset($condVal['$in'])) {
                            return json_encode(array_values($condVal['$in'])) . '.includes(__DATA_VAR__[' . json_encode($condKey) . '])';
                        }

                        return '__DATA_VAR__[' . json_encode($condKey) . '] === ' . json_encode($condVal);
                    })->implode(' && ');

                $visibleIfExpression = str_replace('__DATA_VAR__', $dataVar, $visibleIfExpression);
            }

            return [
                ...$field,
                'key' => $key,
                'field_type' => $field['type'] ?? 'text',
                'visible_if_expression' => $visibleIfExpression,
            ];
        })->values()->all();
    }

    private function resolveExistingSecret(?int $secretId, PollingMethodType $type): Secret
    {
        if (! $secretId) {
            throw ValidationException::withMessages([
                'secret_id' => __('poller.select_credential'),
            ]);
        }

        $secret = Secret::query()->findOrFail($secretId);

        if ($secret->secret_type->value !== $type->value) {
            throw ValidationException::withMessages([
                'secret_id' => __('poller.credential_type_mismatch'),
            ]);
        }

        return $secret;
    }

    private function createSecret(Request $request, PollingMethodType $type, array $validated): Secret
    {
        /** @var class-string<SecretData> $class */
        $class = $type->secretClass();
        $rules = collect($class::rules())
            ->mapWithKeys(fn ($rule, $key) => ["secret_data.{$key}" => $rule])
            ->all();
        $data = $request->validate($rules)['secret_data'] ?? [];

        return Secret::query()->create([
            'description' => $validated['description'] ?: strtoupper($type->value) . ' ' . $request->user()?->user_id,
            'secret_type' => $type->value,
            'default' => (bool) ($validated['default'] ?? false),
            'data' => $data,
        ]);
    }

    private function updateSecret(Request $request, DevicePollingMethod $row, PollingMethodType $type, array $validated): Secret
    {
        /** @var class-string<SecretData> $class */
        $class = $type->secretClass();
        $rules = collect($class::rules())
            ->mapWithKeys(fn ($rule, $key) => ["secret_data.{$key}" => $rule])
            ->all();
        $data = $request->validate($rules)['secret_data'] ?? [];

        $existing = $row->secret;

        if (! $existing) {
            // No secret yet — create one
            return $this->createSecret($request, $type, $validated);
        }

        if (($validated['secret_update_mode'] ?? 'update') === 'create') {
            $new = Secret::query()->create([
                'description' => 'Custom ' . strtoupper($type->value) . ' for ' . $row->device->hostname,
                'secret_type' => $type->value,
                'default' => false,
                'data' => $data,
            ]);

            return $new;
        }

        $existing->update(['data' => $data]);

        return $existing;
    }
}

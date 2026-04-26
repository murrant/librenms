<?php

namespace App\Http\Controllers\Device;

use App\Data\Polling\PollingMethodService;
use App\Data\Secrets\SecretService;
use App\Http\Interfaces\ToastInterface;
use App\Http\Requests\StorePollingMethodRequest;
use App\Http\Requests\UpdatePollingMethodRequest;
use App\Models\Device;
use App\Models\Secret;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use LibreNMS\Enum\PollingMethodType;

class EditPollingController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PollingMethodService $pollingService,
        private readonly SecretService        $secretService,
    ) {}

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
            'device'              => $device,
            'configuredMethods'   => $allMethods->filter(fn (array $m): bool => $m['configured'])->values(),
            'unconfiguredMethods' => $allMethods->filter(fn (array $m): bool => ! $m['configured'])->values(),
            'availableSecrets'    => Secret::query()->orderBy('description')->get()->groupBy(
                fn (Secret $s): string => $s->secret_type->value
            ),
        ]);
    }

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function store(StorePollingMethodRequest $request, Device $device, ToastInterface $toast): RedirectResponse
    {
        $this->authorize('update', $device);

        $validated = $request->validated();
        $type = $request->pollingType() ?? PollingMethodType::from($validated['method_type']);

        $secret = null;
        if ($type->hasSecret()) {
            $this->authorize('create', Secret::class);
            $secret = ($validated['credential_mode'] ?? 'existing') === 'existing'
                ? $this->resolveExistingSecret($validated['secret_id'] ?? null, $type)
                : $this->secretService->create(
                    $type,
                    $request->validatedSecretData(),
                    [
                        'description' => $validated['description'] ?: strtoupper($type->value) . ' ' . $request->user()?->user_id,
                        'default' => (bool) ($validated['default'] ?? false),
                    ]
                );
        }

        $this->pollingService->create($device, $type, $request->validatedSettings(), $secret);

        $toast->success(__('poller.method_added'));

        return redirect()->route('device.edit.polling', ['device' => $device, 'tab' => $type->value]);
    }

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function update(UpdatePollingMethodRequest $request, Device $device, string $methodType, ToastInterface $toast): RedirectResponse
    {
        $this->authorize('update', $device);

        $type          = PollingMethodType::tryFrom($methodType) ?? abort(404);
        $row           = $device->pollingMethods()->where('method_type', $type->value)->firstOrFail();
        $validated = $request->validated();

        if ($type->hasSecret() && array_key_exists('secret_id', $validated)) {
            $this->authorize('update', Secret::class);
            $row->secret_id = $this->resolveExistingSecret((int) $validated['secret_id'], $type)->id;
        } elseif ($type->hasSecret() && $request->has('secret_data')) {
            $this->authorize('update', Secret::class);
            $mode = $validated['secret_update_mode'] ?? 'update';
            $row->secret_id = $this->secretService->updateOrCreate(
                $row,
                $type,
                $request->validatedSecretData(),
                $mode
            )->id;
        }

        $row->setRelation('device', $device);
        $this->pollingService->update($row, $validated, $type);

        $toast->success(__('poller.method_updated'));

        return redirect()->route('device.edit.polling', ['device' => $device, 'tab' => $type->value]);
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Device $device, string $methodType, ToastInterface $toast): RedirectResponse
    {
        $this->authorize('update', $device);

        $type = PollingMethodType::tryFrom($methodType) ?? abort(404);
        $row  = $device->pollingMethods()->where('method_type', $type->value)->firstOrFail();

        if ($type->hasSecret()) {
            $this->authorize('delete', Secret::class);
        }

        $row->delete();

        $toast->success(__('poller.method_removed'));

        return redirect()->route('device.edit.polling', ['device' => $device, 'tab' => $type->value]);
    }

    // ---- Private helpers ----

    private function buildMethodData(Device $device, PollingMethodType $type): array
    {
        $pollingMethod = app($type->methodClass());
        $row           = $device->pollingMethods->firstWhere('method_type', $type);
        $secret        = $row?->secret;
        $schema        = $type->hasSecret() ? $type->secretClass()::getUiSchema() : [];
        $schemaFields  = $this->buildSchemaFields($schema);
        $settingsSchema = $pollingMethod->getSettingsSchema();

        return [
            'type'             => $type->value,
            'label'            => __('poller.methods.' . $type->value),
            'schema_fields'    => $schemaFields,
            'schema_defaults'  => collect($schema)->mapWithKeys(
                fn (array $field, string $key): array => [
                    $key => $field['default'] ?? (isset($field['options']) ? array_key_first($field['options']) : ''),
                ]
            )->all(),
            'settings_fields'  => $this->buildSchemaFields($settingsSchema, 'settingsData'),
            'settings'         => $row?->settings ?? [],
            'affects_availability' => $row?->affects_availability ?? (bool) ($pollingMethod->getDefaults()['affects_availability'] ?? false),
            'secret'           => $secret,
            'secret_form_data' => collect($schema)->mapWithKeys(
                fn (array $field, string $key): array => [
                    $key => (string) data_get($secret?->data, $key, ''),
                ]
            )->all(),
            'usage_count'           => $secret?->devices()->count() ?? 0,
            'configured'            => $row !== null,
            'enabled'               => $row?->enabled ?? false,
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
                'key'                   => $key,
                'field_type'            => $field['type'] ?? 'text',
                'visible_if_expression' => $visibleIfExpression,
            ];
        })->values()->all();
    }

    /**
     * @throws ValidationException
     */
    private function resolveExistingSecret(?int $secretId, PollingMethodType $type): Secret
    {
        if (! $secretId) {
            throw ValidationException::withMessages([
                'secret_id' => __('poller.select_credential'),
            ]);
        }

        return $this->secretService->resolveExisting($secretId, $type);
    }
}

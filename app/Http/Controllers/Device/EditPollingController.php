<?php

namespace App\Http\Controllers\Device;

use App\Data\Secrets\SecretData;
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

        $methods = collect(SecretType::cases())->map(function (SecretType $type) use ($device): array {
            $secret = $device->secrets->first(fn (Secret $secret): bool => $secret->secret_type === $type);

            return [
                'type' => $type,
                'label' => strtoupper($type->value),
                'schema' => $type->secretClass()::getUiSchema(),
                'secret' => $secret,
                'enabled' => $this->isMethodEnabled($device, $type),
                'last_check_successful' => $this->lastCheckSuccessful($device, $type, $secret),
            ];
        });

        return view('device.edit.polling', [
            'device' => $device,
            'methods' => $methods,
            'unconfiguredMethods' => $methods->whereNull('secret')->values(),
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
        ]);

        if (Arr::has($validated, 'secret_id')) {
            $secret = $this->resolveExistingSecret((int) $validated['secret_id'], $type);
            $device->secrets()->updateExistingPivot($secret->id, ['secret_type' => $type->value]);
        }

        if ($type === SecretType::Snmp) {
            $device->snmp_disable = (bool) ($validated['disabled'] ?? false);
            $device->save();
        }

        $toast->success(__('Polling method updated'));

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
            default => true,
        };
    }

    private function lastCheckSuccessful(Device $device, SecretType $type, ?Secret $secret): ?bool
    {
        if (! $secret) {
            return null;
        }

        return match ($type) {
            SecretType::Snmp => $device->last_polled !== null && ! $device->snmp_disable,
            default => null,
        };
    }
}

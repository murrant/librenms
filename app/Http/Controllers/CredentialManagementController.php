<?php

namespace App\Http\Controllers;

use App\Models\Credential;
use App\Repositories\CredentialRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CredentialManagementController extends Controller
{
    public function __construct(protected CredentialRepository $credentialRepository)
    {
    }

    public function index(): View
    {
        Gate::authorize('credential.viewAny');

        return view('credentials.index', [
            'credentials' => Credential::withCount('devices')->get(),
            'types' => $this->credentialRepository->getAvailableTypes(),
        ]);
    }

    public function devices(Credential $credential): JsonResponse
    {
        Gate::authorize('credential.viewAny');

        $devices = $credential->devices()->get(['devices.device_id', 'hostname', 'sysName', 'display']);

        return response()->json([
            'devices' => $devices->map(function ($device) {
                return [
                    'device_id' => $device->device_id,
                    'hostname' => $device->hostname,
                    'sysName' => $device->sysName,
                    'display' => $device->display,
                    'url' => route('device.show', ['device' => $device->device_id]),
                ];
            }),
        ]);
    }

    public function schema(string $type): JsonResponse
    {
        Gate::authorize('credential.viewAny');

        $typeInfo = $this->credentialRepository->getTypeInfo($type);

        if (! $typeInfo) {
            return response()->json(['error' => 'Invalid type'], 400);
        }

        return response()->json([
            'schema' => $typeInfo['schema'],
            'ui' => $typeInfo['ui'],
        ]);
    }

    public function unmask(Credential $credential, string $field): JsonResponse
    {
        Gate::authorize('credential.unmask');

        $data = $credential->data;

        if (! isset($data[$field])) {
            return response()->json(['error' => 'Field not found'], 404);
        }

        return response()->json(['value' => $data[$field]]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('credential.create');

        $validated = $request->validate([
            'name' => 'required|string|unique:credentials,name',
            'type' => 'required|string',
            'data' => 'required|string',
            'is_default' => 'boolean',
        ]);

        $data = json_decode($validated['data'], true) ?: [];
        $typeClass = $validated['type'];

        $data = $this->credentialRepository->parseData($typeClass, $data);

        Credential::create([
            'name' => $validated['name'],
            'type' => $typeClass,
            'data' => $data,
            'is_default' => $validated['is_default'] ?? false,
        ]);

        toast()->success('Credential created');

        return redirect()->route('credentials.index');
    }

    public function update(Request $request, Credential $credential): RedirectResponse
    {
        Gate::authorize('credential.update');

        $validated = $request->validate([
            'name' => 'required|string|unique:credentials,name,' . $credential->id,
            'data' => 'required|string',
            'is_default' => 'boolean',
        ]);

        $data = json_decode($validated['data'], true) ?: [];

        $data = $this->credentialRepository->prepareUpdateData($credential, $data);

        $credential->update([
            'name' => $validated['name'],
            'data' => $data,
            'is_default' => $validated['is_default'] ?? false,
        ]);

        toast()->success('Credential updated');

        return redirect()->route('credentials.index');
    }

    public function destroy(Credential $credential): RedirectResponse
    {
        Gate::authorize('credential.delete');

        $credential->delete();

        toast()->success('Credential deleted');

        return redirect()->route('credentials.index');
    }
}

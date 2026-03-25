<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Credential;
use App\Repositories\CredentialRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CredentialController extends Controller
{
    public function __construct(protected CredentialRepository $credentialRepository)
    {
    }

    public function index(): JsonResponse
    {
        Gate::authorize('credential.viewAny');

        $credentials = Credential::all()->map(function ($credential) {
            return $this->formatCredential($credential, false);
        });

        return response()->json(['credentials' => $credentials]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('credential.create');

        $validated = $request->validate([
            'name' => 'required|string|unique:credentials,name',
            'type' => 'required|string',
            'data' => 'required|array',
            'is_default' => 'boolean',
        ]);

        $validated['data'] = $this->credentialRepository->parseData($validated['type'], $validated['data']);

        $credential = Credential::create($validated);

        return response()->json([
            'message' => 'Credential created',
            'credential' => $this->formatCredential($credential, true),
        ], 201);
    }

    public function show(Credential $credential): JsonResponse
    {
        Gate::authorize('credential.view');

        $unmask = Gate::allows('credential.unmask');

        return response()->json(['credential' => $this->formatCredential($credential, $unmask)]);
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

    public function update(Request $request, Credential $credential): JsonResponse
    {
        Gate::authorize('credential.update');

        $validated = $request->validate([
            'name' => 'string|unique:credentials,name,' . $credential->id,
            'type' => 'string',
            'data' => 'array',
            'is_default' => 'boolean',
        ]);

        if (isset($validated['data'])) {
            $typeClass = $validated['type'] ?? $credential->type;
            $validated['data'] = $this->credentialRepository->parseData($typeClass, $validated['data']);
        }

        $credential->update($validated);

        return response()->json([
            'message' => 'Credential updated',
            'credential' => $this->formatCredential($credential, true),
        ]);
    }

    public function destroy(Credential $credential): JsonResponse
    {
        Gate::authorize('credential.delete');

        $credential->delete();

        return response()->json(['message' => 'Credential deleted']);
    }

    private function formatCredential(Credential $credential, bool $unmask): array
    {
        return [
            'id' => $credential->id,
            'name' => $credential->name,
            'type' => $credential->type,
            'version' => $credential->version,
            'is_default' => $credential->is_default,
            'data' => $this->credentialRepository->formatData($credential, $unmask),
            'created_at' => $credential->created_at,
            'updated_at' => $credential->updated_at,
        ];
    }
}

<?php
/**
 * CredentialController.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2026 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Http\Controllers;

use App\Http\Interfaces\ToastInterface;
use App\Models\Credential;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LibreNMS\Enum\CredentialType;

class CredentialController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Credential::class);

        return view('credentials.index', [
            'credentials' => Credential::orderBy('description')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Credential::class);

        $type = $request->query('type', 'snmp');
        $credentialType = CredentialType::tryFrom($type) ?? CredentialType::Snmp;
        $schema = $credentialType->credentialClass()::getUiSchema();

        return view('credentials.create', [
            'types' => CredentialType::cases(),
            'currentType' => $credentialType,
            'schema' => $schema,
        ]);
    }

    public function store(Request $request, ToastInterface $toast): RedirectResponse
    {
        Gate::authorize('create', Credential::class);

        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'credential_type' => 'required|string',
            'default' => 'boolean',
        ]);

        $credentialType = CredentialType::tryFrom($validated['credential_type']);
        if (!$credentialType) {
            abort(400, 'Invalid credential type.');
        }

        $class = $credentialType->credentialClass();
        $rules = $class::rules();
        $data = $request->validate($rules);

        Credential::create([
            'description' => $validated['description'],
            'credential_type' => $credentialType,
            'default' => $request->boolean('default'),
            'data' => $data,
        ]);

        $toast->success(__('Credential created'));

        return redirect()->route('credentials.index');
    }

    public function edit(Credential $credential): View
    {
        Gate::authorize('update', $credential);

        $credentialType = $credential->credential_type;
        $schema = $credentialType->credentialClass()::getUiSchema();
        $data = Gate::allows('unmask', $credential)
            ? $credential->data
            : $this->maskPasswordFields($credential->data, $schema);

        return view('credentials.edit', [
            'credential' => $credential,
            'schema' => $schema,
            'data' => $data,
        ]);
    }

    public function update(Request $request, Credential $credential, ToastInterface $toast): RedirectResponse
    {
        Gate::authorize('update', $credential);

        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'default'     => 'boolean',
        ]);

        $credentialType = $credential->credential_type;
        $class = $credentialType->credentialClass();
        $data = $request->validate($class::rules());

        if (! Gate::allows('unmask', $credential)) {
            $schema = $class::getUiSchema();
            $data = $this->restoreMaskedFields($data, $credential->data, $schema);
        }

        $credential->update([
            'description' => $validated['description'],
            'default'     => $request->boolean('default'),
            'data'        => $data,
        ]);

        $toast->success(__('Credential updated'));

        return redirect()->route('credentials.index');
    }

    public function destroy(Credential $credential, ToastInterface $toast): RedirectResponse
    {
        Gate::authorize('delete', $credential);

        $credential->delete();

        $toast->success(__('Credential deleted'));

        return redirect()->route('credentials.index');
    }

    private function maskPasswordFields(array $data, array $schema): array
    {
        foreach ($schema as $field => $config) {
            if (($config['type'] ?? null) === 'password' && ! empty($data[$field])) {
                $data[$field] = '__MASKED__';
            }
        }

        return $data;
    }

    private function restoreMaskedFields(array $newData, array $originalData, array $schema): array
    {
        foreach ($schema as $field => $config) {
            if (($config['type'] ?? null) === 'password') {
                if (($newData[$field] ?? null) === '__MASKED__') {
                    $newData[$field] = $originalData[$field] ?? null;
                }
            }
        }

        return $newData;
    }
}

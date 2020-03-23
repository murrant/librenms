<?php

namespace App\Http\Controllers;

use App\Models\PollerGroups;
use App\Models\Port;
use App\Models\User;
use App\Models\UserPref;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LibreNMS\Config;
use Str;

class DeviceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function create()
    {
        $this->authorize('create', \App\Models\Device::class);

        /** @var User $user */
        $user = auth()->user();

        return view('device.create', [
            'data' => [
                'advanced' => UserPref::getPref($user, 'device_add_advanced'),
                'port_association' => Config::get('default_port_association_mode'),
                'default_poller_group' => Config::get('distributed_poller_group'),
            ],
            'port_association_modes' => Port::associationModes(),
            'poller_groups' => PollerGroups::query()->orderBy('group_name')->pluck('group_name', 'id'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('create', $request->user());

        $v3auth = $request->get('type') == 'snmpv3' && in_array($request->get('auth_level'), ['authNoPriv', 'authPriv']);
        $v3crypto = $request->get('type') == 'snmpv3' && $request->get('auth_level') == 'authPriv';

        $this->validate($request, [
            'type' => 'required|in:snmpv1,snmpv2,snmpv3,ping',
            'hostname' => 'required|ip_or_hostname',
            'override_ip' => 'nullable|ip',
            'proto' => 'required|in:udp,tcp',
            'transport' => 'required|in:4,6,auto',
            'port' => 'nullable|integer|between:0,65535',
            'community' => 'nullable|string',
            'sysname' => 'nullable|string',
            'os' => 'nullable|string',
            'hardware' => 'nullable|string',
            'poller_group' => 'nullable|integer',
            'port_association' => ['required_unless:type,ping', Rule::in(Port::associationModes())],
            'auth_level' => 'required_if:type,snmpv3|in:noAuthNoPriv,authNoPriv,authPriv',
            'auth_algo' => [Rule::requiredIf($v3auth), Rule::in(['MD5', 'SHA'])],
            'auth_name' => [Rule::requiredIf($v3auth), 'nullable', 'string'],
            'auth_pass' => [Rule::requiredIf($v3auth), 'nullable', 'string'],
            'crypto_algo' => [Rule::requiredIf($v3crypto), Rule::in(['AES', 'DES'])],
            'crypto_pass' => [Rule::requiredIf($v3crypto), 'nullable', 'string'],
        ]);

        $all = $request->all();
        $all['device_id'] = rand(1, 432);
        if (rand(0,1)) {
            return response()->json($all);
        } else {
            return response()->json($all, 406);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}

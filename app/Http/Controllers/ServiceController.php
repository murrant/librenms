<?php

namespace App\Http\Controllers;

use App\Http\Interfaces\ToastInterface;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function store(Request $request, ToastInterface $toast)
    {
        $service = Service::create($request->validate([
            'service_name' => 'required|string|unique:service',
            'device_id' => 'integer',
            'service_type' => 'string',
            'service_param' => 'nullable|string',
            'service_ip' => 'nullable|string',
            'service_desc' => 'nullable|string',
            'service_disabled' => 'integer',
            'service_ignore' => 'integer',
        ]));

        $toast->success(__('Service :name created', ['name' => $service->service_name]));

        return redirect()->route('services.templates.index');
    }
}

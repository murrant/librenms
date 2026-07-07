<?php

namespace App\Http\Controllers;

use App\Http\Interfaces\ToastInterface;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     */
    public function store(Request $request, ToastInterface $toast)
    {
        $this->authorize('create', Service::class);

        $rules = [
            'device_id' => 'required|integer|exists:devices,device_id',
            'service_changed' => 'nullable|integer',
            'service_template_id' => 'nullable|integer',
        ];

        if ($request->has('service_name')) {
            $rules['service_name'] = 'required|string|unique:services,service_name';
            $rules['service_type'] = 'required|string';
            $rules['service_param'] = 'nullable|string';
            $rules['service_ip'] = 'nullable|string';
            $rules['service_desc'] = 'nullable|string';
            $rules['service_disabled'] = 'nullable|integer';
            $rules['service_ignore'] = 'nullable|integer';
        } else {
            $rules['name'] = 'required|string|unique:services,service_name';
            $rules['stype'] = 'required|string';
            $rules['param'] = 'nullable|string';
            $rules['ip'] = 'nullable|string';
            $rules['desc'] = 'nullable|string';
            $rules['disabled'] = 'nullable|integer';
            $rules['ignore'] = 'nullable|integer';
        }

        $this->validate($request, $rules);

        $name = strip_tags((string) $request->input('service_name', $request->input('name')));
        $type = strip_tags((string) $request->input('service_type', $request->input('stype')));
        $desc = strip_tags((string) $request->input('service_desc', $request->input('desc', '')));
        $ip = $request->input('service_ip', $request->input('ip', ''));
        $param = $request->input('service_param', $request->input('param', ''));
        $ignore = (int) $request->input('service_ignore', $request->input('ignore', 0));
        $disabled = (int) $request->input('service_disabled', $request->input('disabled', 0));
        $templateId = $request->input('service_template_id', $request->input('template_id', 0));
        $deviceId = $request->input('device_id');

        $service = \LibreNMS\Services::addService($deviceId, $type, $desc, $ip, $param, $ignore, $disabled, $templateId, $name);

        if ($request->expectsJson() || $request->wantsJson()) {
            if ($service) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Added Service: <i>' . $service->service_id . ': ' . e($type) . '</i>',
                ]);
            }

            return response()->json([
                'status' => 1,
                'message' => 'ERROR: Failed to add Service: <i>' . e($type) . '</i>',
            ]);
        }

        $toast->success(e(__('Service :name created', ['name' => $name])));

        return redirect()->route('services.templates.index');
    }

    /**
     * Display the specified service.
     *
     * @param  Service  $service
     * @return JsonResponse
     */
    public function show(Service $service): JsonResponse
    {
        $this->authorize('view', $service);

        return response()->json([
            'stype' => $service->service_type,
            'ip' => $service->service_ip,
            'desc' => $service->service_desc,
            'param' => $service->service_param,
            'ignore' => $service->service_ignore,
            'disabled' => $service->service_disabled,
            'template_id' => $service->service_template_id,
            'name' => $service->service_name,
        ]);
    }

    /**
     * Update the specified service in storage.
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);

        $rules = [
            'service_changed' => 'nullable|integer',
            'service_template_id' => 'nullable|integer',
            'service_name' => 'sometimes|required|string|unique:services,service_name,' . $service->service_id . ',service_id',
            'service_type' => 'sometimes|required|string',
            'service_param' => 'nullable|string',
            'service_ip' => 'nullable|string',
            'service_desc' => 'nullable|string',
            'service_disabled' => 'nullable|integer',
            'service_ignore' => 'nullable|integer',
            'name' => 'sometimes|required|string|unique:services,service_name,' . $service->service_id . ',service_id',
            'stype' => 'sometimes|required|string',
            'param' => 'nullable|string',
            'ip' => 'nullable|string',
            'desc' => 'nullable|string',
            'disabled' => 'nullable|integer',
            'ignore' => 'nullable|integer',
        ];

        $this->validate($request, $rules);

        if ($request->has('service_name') || $request->has('name')) {
            $service->service_name = strip_tags((string) $request->input('service_name', $request->input('name')));
        }
        if ($request->has('service_type') || $request->has('stype')) {
            $service->service_type = strip_tags((string) $request->input('service_type', $request->input('stype')));
        }
        if ($request->has('service_desc') || $request->has('desc')) {
            $service->service_desc = strip_tags((string) $request->input('service_desc', $request->input('desc', '')));
        }
        if ($request->has('service_ip') || $request->has('ip')) {
            $service->service_ip = $request->input('service_ip', $request->input('ip'));
        }
        if ($request->has('service_param') || $request->has('param')) {
            $service->service_param = $request->input('service_param', $request->input('param'));
        }
        if ($request->has('service_ignore') || $request->has('ignore')) {
            $service->service_ignore = (int) $request->input('service_ignore', $request->input('ignore'));
        }
        if ($request->has('service_disabled') || $request->has('disabled')) {
            $service->service_disabled = (int) $request->input('service_disabled', $request->input('disabled'));
        }
        if ($request->has('service_template_id') || $request->has('template_id')) {
            $service->service_template_id = $request->input('service_template_id', $request->input('template_id')) ?: null;
        }

        if ($service->save()) {
            return response()->json([
                'status' => 0,
                'message' => 'Modified Service: <i>' . e($service->service_id) . ': ' . e($service->service_type) . '</i>',
            ]);
        }

        return response()->json([
            'status' => 1,
            'message' => 'ERROR: Failed to modify service: <i>' . e($service->service_id) . '</i>',
        ]);
    }

    /**
     * Remove the specified service from storage.
     *
     * @param  Service  $service
     * @return JsonResponse
     */
    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $id = $service->service_id;

        if ($service->delete()) {
            return response()->json([
                'status' => 0,
                'message' => 'Service: <i>' . e($id) . ', has been deleted.</i>',
            ]);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Service: <i>' . e($id) . ', has NOT been deleted.</i>',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlertTemplateRequest;
use App\Models\AlertTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AlertTemplateController extends Controller
{
    public function show(AlertTemplate $alertTemplate): JsonResponse
    {
        $this->authorize('view', $alertTemplate);

        $output = $alertTemplate->only(['template', 'name', 'title', 'title_rec']);
        $output['rules'] = $alertTemplate->alert_rules()
            ->pluck('alert_rules.name', 'alert_rules.id');

        return response()->json($output);
    }

    public function store(AlertTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', AlertTemplate::class);

        $alertTemplate = DB::transaction(function () use ($request) {
            $template = AlertTemplate::create($request->validated());
            $template->alert_rules()->sync($request->input('rules', []));

            return $template;
        });

        return response()->json([
            'status' => 'ok',
            'message' => 'Alert template has been created and attached rules have been updated.',
            'newid' => $alertTemplate->id,
        ]);
    }

    public function update(AlertTemplateRequest $request, AlertTemplate $alertTemplate): JsonResponse
    {
        $this->authorize('update', $alertTemplate);

        DB::transaction(function () use ($request, $alertTemplate) {
            $alertTemplate->update($request->validated());
            $alertTemplate->alert_rules()->sync($request->input('rules', []));
        });

        return response()->json([
            'status' => 'ok',
            'message' => 'Alert template has been updated and attached rules have been updated.',
        ]);
    }

    public function destroy(AlertTemplate $alertTemplate): JsonResponse
    {
        $this->authorize('delete', $alertTemplate);

        $alertTemplate->delete();

        return response()->json([
            'message' => 'Alert template deleted.',
        ]);
    }
}

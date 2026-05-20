<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Models\AlertTemplate;
use Illuminate\Http\JsonResponse;

class AlertTemplateController extends Controller
{
    public function show(int $templateId = 0): JsonResponse
    {
        $alertTemplate = AlertTemplate::find($templateId);

        if ($alertTemplate) {
            $this->authorize('view', $alertTemplate);
            $output = $alertTemplate->only(['template', 'name', 'title', 'title_rec']);
            $selectedRuleIds = $alertTemplate->alert_rules()->pluck('alert_rules.id')->flip();
        } else {
            $output = ['template' => '', 'name' => '', 'title' => '', 'title_rec' => ''];
            $selectedRuleIds = collect();
        }

        $output['rules'] = AlertRule::select(['id', 'name'])
            ->with('templateMaps.template:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (AlertRule $rule) => [
                'id'       => $rule->id,
                'name'     => $rule->name,
                'selected' => $selectedRuleIds->has($rule->id),
                'used'     => $rule->templateMaps->first()?->template?->name ?? '',
            ]);

        return response()->json($output, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function destroy(AlertTemplate $alertTemplate)
    {
        $this->authorize('delete', $alertTemplate);

        $alertTemplate->delete();
    }
}

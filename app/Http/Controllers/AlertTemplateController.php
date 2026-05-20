<?php

namespace App\Http\Controllers;

use App\Models\AlertTemplate;
use Illuminate\Http\JsonResponse;

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

    public function destroy(AlertTemplate $alertTemplate): JsonResponse
    {
        $this->authorize('delete', $alertTemplate);

        $alertTemplate->delete();

        return response()->json([
            'message' => 'Alert template deleted.',
        ]);
    }
}

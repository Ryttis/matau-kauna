<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicStatisticsConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicStatisticsConfigController extends Controller
{
    public function show(): JsonResponse
    {
        $config = PublicStatisticsConfig::get();

        return response()->json(['config' => $config]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'config' => ['required', 'array'],
            'config.kpis' => ['array'],
            'config.kpis.*' => ['string', 'in:received_reports,avg_resolution_days,resolved_problems,resolved_on_time,monthly_reports,problematic_categories,effectively_resolved'],
            'config.default_period' => ['string', 'in:week,month,quarter,year,all'],
            'config.period_options' => ['array'],
            'config.period_options.*' => ['string', 'in:week,month,quarter,year,all'],
            'config.show_category_filter' => ['boolean'],
        ]);

        PublicStatisticsConfig::set($validated['config']);

        return response()->json(['ok' => true]);
    }
}

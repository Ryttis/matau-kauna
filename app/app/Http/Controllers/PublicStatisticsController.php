<?php

namespace App\Http\Controllers;

use App\Models\ClassifierValue;
use App\Models\PublicStatisticsConfig;
use App\Services\StatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicStatisticsController extends Controller
{
    public function __construct(
        private StatisticsService $statisticsService
    ) {}

    public function data(Request $request): JsonResponse
    {
        $config = PublicStatisticsConfig::get();
        $period = $request->input('period', $config['default_period'] ?? 'year');
        $category = $request->input('category', 'all');

        $query = $this->statisticsService->buildPublicProblemQuery($period, $category);
        $analytics = $this->statisticsService->getAnalytics($query);

        $categories = ClassifierValue::getActiveTypeNames()->values()->all();

        return response()->json([
            'config' => $config,
            'data' => [
                'receivedReports' => $analytics['receivedReports'],
                'averageResolutionTime' => $analytics['averageResolutionTime'],
                'resolvedProblems' => $analytics['resolvedProblems'],
                'resolvedOnTime' => $analytics['resolvedOnTime'],
                'monthlyReports' => $analytics['monthlyReports'],
                'problematicCategories' => $analytics['problematicCategories'],
                'effectivelyResolved' => $analytics['effectivelyResolved'],
            ],
            'categories' => $categories,
        ]);
    }
}

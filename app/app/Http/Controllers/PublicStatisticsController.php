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
        $allowedPeriods = $config['period_options'] ?? ['year', 'all'];
        $defaultPeriod = $config['default_period'] ?? 'year';
        if (! in_array($defaultPeriod, $allowedPeriods, true)) {
            $defaultPeriod = $allowedPeriods[0] ?? 'year';
        }

        $rawPeriod = $request->query('period');
        if ($rawPeriod === null || $rawPeriod === '') {
            $period = $defaultPeriod;
        } else {
            $period = in_array((string) $rawPeriod, $allowedPeriods, true)
                ? (string) $rawPeriod
                : $defaultPeriod;
        }

        $category = $request->input('category', 'all');
        if (! ($config['show_category_filter'] ?? true)) {
            $category = 'all';
        }

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

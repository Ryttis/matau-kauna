<?php

namespace App\Http\Controllers;

use App\Models\ClassifierValue;
use App\Models\PublicStatisticsConfig;
use App\Services\StatisticsService;
use Carbon\Carbon;
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
        $configFrom = $this->parseDate($config['date_from'] ?? null);
        $configTo = $this->parseDate($config['date_to'] ?? null);

        $userFrom = $this->parseDate($request->input('date_from'));
        $userTo = $this->parseDate($request->input('date_to'));

        $effectiveFrom = $this->clampLowerBound($userFrom, $configFrom, $configTo);
        $effectiveTo = $this->clampUpperBound($userTo, $configFrom, $configTo);

        $category = $request->input('category', 'all');
        if (! ($config['show_category_filter'] ?? true)) {
            $category = 'all';
        }

        $query = $this->statisticsService->buildPublicProblemQuery(
            $effectiveFrom?->toDateString(),
            $effectiveTo?->toDateString(),
            $category,
        );

        $analytics = $this->statisticsService->getAnalytics($query, $effectiveFrom, $effectiveTo);

        $categories = ClassifierValue::getActiveTypeNames()->values()->all();

        return response()->json([
            'config' => $config,
            'effective' => [
                'date_from' => $effectiveFrom?->toDateString(),
                'date_to' => $effectiveTo?->toDateString(),
            ],
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

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function clampLowerBound(?Carbon $userFrom, ?Carbon $configFrom, ?Carbon $configTo): ?Carbon
    {
        $value = $userFrom ?? $configFrom;
        if ($configFrom && $value && $value->lt($configFrom)) {
            $value = $configFrom;
        }
        if ($configTo && $value && $value->gt($configTo)) {
            $value = $configTo;
        }

        return $value;
    }

    private function clampUpperBound(?Carbon $userTo, ?Carbon $configFrom, ?Carbon $configTo): ?Carbon
    {
        $value = $userTo ?? $configTo;
        if ($configTo && $value && $value->gt($configTo)) {
            $value = $configTo;
        }
        if ($configFrom && $value && $value->lt($configFrom)) {
            $value = $configFrom;
        }

        return $value;
    }
}

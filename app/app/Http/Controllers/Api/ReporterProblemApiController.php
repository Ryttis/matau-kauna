<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProblemStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassifierValue;
use App\Models\FileUploadSettings;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ProblemHttpSupport;
use App\Services\ProblemMergeContextService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReporterProblemApiController extends Controller
{
    public function __construct(
        private ProblemHttpSupport $problemHttpSupport,
        private ProblemMergeContextService $mergeContextService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $reporter = $this->authenticatedReporterOrAbort($request);
        $category = $this->normalizeCategoryFilter($request->query('category'));

        $filters = [
            'search' => $request->query('search'),
            'report_number' => $request->query('report_number'),
            'category' => $category,
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $problems = $this->problemHttpSupport->reporterProblemsFlatOrderedForList($reporter, $filters)
            ->map(fn ($problem) => [
                'id' => $problem->id,
                'internal_number' => $problem->internal_number,
                'status' => $problem->status,
                'created_at' => $problem->created_at?->toIso8601String(),
                'category' => $problem->category,
                'address' => $problem->address,
                'description' => $problem->description ?? '',
                'is_published_publicly' => $problem->is_published_publicly,
                'merged_into_problem_id' => $problem->merged_into_problem_id,
            ])
            ->values()
            ->all();

        return response()->json([
            'problems' => $problems,
            'categories' => ClassifierValue::getActiveTypeNames()->values()->all(),
            'filters' => $filters,
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $reporter = $this->authenticatedReporterOrAbort($request);
        [$dateFrom, $dateTo] = $this->normalizeDateRangeFromPeriod(
            $request->query('period'),
            $request->query('date_from'),
            $request->query('date_to')
        );
        $category = $this->normalizeCategoryFilter($request->query('category'));

        $filters = [
            'period' => $request->query('period'),
            'category' => $category,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        $problems = $this->problemHttpSupport->reporterProblemsFlatOrderedForList($reporter, $filters);

        $total = $problems->count();
        $solvedCount = $problems->where('status', ProblemStatus::RESOLVED->value)->count();

        $countsByCategory = $problems->groupBy('category')
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->all();
        $byCategory = array_map(
            fn ($category, $count) => ['category' => $category, 'count' => $count],
            array_keys($countsByCategory),
            $countsByCategory
        );

        $monthlyReports = $this->problemHttpSupport->buildReporterMonthlyReports($problems);

        $nagrinejamosProblemos = $problems->where('status', ProblemStatus::IN_REVIEW->value)
            ->sortByDesc('created_at')
            ->values()
            ->map(fn ($problem) => $this->problemHttpSupport->mapProblemToStatisticsListItem($problem))
            ->all();

        $issprestosProblemos = $problems->where('status', ProblemStatus::RESOLVED->value)
            ->sortByDesc('created_at')
            ->values()
            ->map(fn ($problem) => $this->problemHttpSupport->mapProblemToStatisticsListItem($problem))
            ->all();

        return response()->json([
            'categories' => ClassifierValue::getActiveTypeNames()->values()->all(),
            'filters' => $filters,
            'total' => $total,
            'solvedCount' => $solvedCount,
            'byCategory' => array_values($byCategory),
            'monthlyReports' => $monthlyReports,
            'nagrinejamosProblemos' => $nagrinejamosProblemos,
            'issprestosProblemos' => $issprestosProblemos,
        ]);
    }

    public function reportMeta(Request $request): JsonResponse
    {
        $reporter = $this->authenticatedReporterOrAbort($request);

        $types = ClassifierValue::notArchived()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with('activeChildren')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $typeValues = $types->map(fn (ClassifierValue $type) => [
            'id' => $type->id,
            'name' => $type->name,
            'natures' => $type->activeChildren->map(fn (ClassifierValue $nature) => [
                'id' => $nature->id,
                'name' => $nature->name,
            ])->values()->all(),
        ])->values()->all();

        return response()->json([
            'typeValues' => $typeValues,
            'showReporterWizard' => $reporter->reporter_wizard_completed_at === null,
            'hasAcceptedReportTerms' => $reporter->reporter_report_terms_accepted_at !== null,
            'fileUpload' => FileUploadSettings::getReporterReportChannelForClient(),
        ]);
    }

    public function show(Request $request, Problem $problem): JsonResponse
    {
        $reporter = $this->authenticatedReporterOrAbort($request);

        if ($problem->is_archived) {
            abort(404);
        }

        $isOwnReport = (int) $problem->reported_by_user_id === (int) $reporter->id;
        if (! $isOwnReport && ! $problem->is_published_publicly) {
            abort(404);
        }

        $problem->load('attachments');

        $mergeContext = $this->mergeContextService->forProblem($problem);

        return response()->json([
            'problem' => [
                'id' => $problem->id,
                'internal_number' => $problem->internal_number,
                'status' => $problem->status,
                'category' => $problem->category,
                'address' => $problem->address,
                'description' => $problem->description,
                'created_at' => $problem->created_at?->toIso8601String(),
                'is_published_publicly' => $problem->is_published_publicly,
                'attachments' => $this->problemHttpSupport->attachmentsPayload($problem),
            ],
            'mergeTarget' => $mergeContext['mergeTarget'],
            'mergedChildren' => $mergeContext['mergedChildren'],
            'mergedSiblings' => $mergeContext['mergedSiblings'],
        ]);
    }

    private function authenticatedReporterOrAbort(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->hasRole(UserRole::ROLE_REPORTER)) {
            abort(403, 'Prieinama tik pareiškėjams.');
        }

        return $user;
    }

    private function normalizeCategoryFilter(mixed $category): ?string
    {
        $value = is_string($category) ? trim($category) : '';

        return ($value === '' || strtolower($value) === 'all') ? null : $value;
    }

    private function normalizeDateRangeFromPeriod(mixed $period, mixed $dateFrom, mixed $dateTo): array
    {
        $from = is_string($dateFrom) ? trim($dateFrom) : '';
        $to = is_string($dateTo) ? trim($dateTo) : '';

        if ($from !== '' || $to !== '') {
            return [$from !== '' ? $from : null, $to !== '' ? $to : null];
        }

        $periodValue = is_string($period) ? trim($period) : '';
        if ($periodValue === '' || $periodValue === 'all') {
            return [null, null];
        }

        $today = Carbon::today();

        return match ($periodValue) {
            '6' => [$today->copy()->subMonths(6)->format('Y-m-d'), $today->format('Y-m-d')],
            '12' => [$today->copy()->subMonths(12)->format('Y-m-d'), $today->format('Y-m-d')],
            'year' => [$today->copy()->startOfYear()->format('Y-m-d'), $today->format('Y-m-d')],
            'week' => [$today->copy()->subDays(7)->format('Y-m-d'), $today->format('Y-m-d')],
            'month' => [$today->copy()->subMonth()->format('Y-m-d'), $today->format('Y-m-d')],
            'quarter' => [$today->copy()->subMonths(3)->format('Y-m-d'), $today->format('Y-m-d')],
            default => [null, null],
        };
    }
}

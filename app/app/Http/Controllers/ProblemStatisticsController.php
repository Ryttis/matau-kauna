<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Exports\StatisticsReportExport;
use App\Models\ClassifierValue;
use App\Models\Institution;
use App\Models\Problem;
use App\Services\ProblemDeadlineMonitorService;
use App\Services\ProblemScopeService;
use App\Services\StatisticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ProblemStatisticsController extends Controller
{
    public function __construct(
        private ProblemScopeService $scopeService,
    ) {}

    public function statistics(Request $request): Response
    {
        $user = $request->user();

        $route = $request->route()->getName();

        if ($route === 'statistics.resolution-terms') {
            $query = Problem::query()
                ->notArchived()
                ->whereNotNull('completion_deadline')
                ->whereNull('merged_into_problem_id')
                ->with(['institution:id,name', 'assignedEmployee:id,name,surname,email']);
            $this->scopeService->scopeProblemsForUser($query, $user);
            $all = $query->orderBy('completion_deadline')->get();
            $today = now()->startOfDay();
            $approachingEnd = $today->copy()->addDays(ProblemDeadlineMonitorService::REMIND_DAYS_BEFORE);
            $resolved = ProblemStatus::RESOLVED->value;
            $approaching = $all->filter(fn ($problem) => $problem->completion_deadline && $problem->completion_deadline->gte($today) && $problem->completion_deadline->lte($approachingEnd) && $problem->status !== $resolved);
            $overdue = $all->filter(fn ($problem) => $problem->completion_deadline && $problem->completion_deadline->lt($today) && $problem->status !== $resolved);

            return Inertia::render('Statistics/ResolutionTerms', [
                'approaching' => $approaching->values()->map(fn ($problem) => [
                    'id' => $problem->id,
                    'internal_number' => $problem->internal_number,
                    'description_preview' => Problem::descriptionPreview($problem->description, 120),
                    'status' => $problem->status,
                    'completion_deadline' => $problem->completion_deadline?->format('Y-m-d'),
                    'institution_name' => $problem->institution?->name,
                    'assigned_to' => $problem->assignedEmployee ? trim($problem->assignedEmployee->name.' '.$problem->assignedEmployee->surname) : null,
                ])->all(),
                'overdue' => $overdue->values()->map(fn ($problem) => [
                    'id' => $problem->id,
                    'internal_number' => $problem->internal_number,
                    'description_preview' => Problem::descriptionPreview($problem->description, 120),
                    'status' => $problem->status,
                    'completion_deadline' => $problem->completion_deadline?->format('Y-m-d'),
                    'institution_name' => $problem->institution?->name,
                    'assigned_to' => $problem->assignedEmployee ? trim($problem->assignedEmployee->name.' '.$problem->assignedEmployee->surname) : null,
                ])->all(),
            ]);
        }

        $service = app(StatisticsService::class);
        $period = $request->input('period', 'all');
        $institution = $request->input('institution', 'all');
        $category = $request->input('category', 'all');

        $institutions = $service->institutionsForUser($user)->map(fn ($i) => ['id' => $i->id, 'name' => $i->name])->values()->all();
        $categories = ClassifierValue::getActiveTypeNames()->values()->all();

        $query = $service->buildProblemQuery($user, $period, $institution, $category);
        $analytics = $service->getAnalytics($query);

        return Inertia::render('Statistics', [
            'categories' => $categories,
            'institutions' => $institutions,
            'filters' => [
                'period' => $period,
                'institution' => $institution,
                'category' => $category,
            ],
            'activeUsers' => $analytics['activeUsers'],
            'receivedReports' => $analytics['receivedReports'],
            'averageResolutionTime' => $analytics['averageResolutionTime'],
            'resolvedProblems' => $analytics['resolvedProblems'],
            'resolvedOnTime' => $analytics['resolvedOnTime'],
            'improvement' => $analytics['improvement'],
            'monthlyReports' => $analytics['monthlyReports'],
            'problematicCategories' => $analytics['problematicCategories'],
            'effectivelyResolved' => $analytics['effectivelyResolved'],
            'employeeActivity' => $analytics['employeeActivity'],
            'institutionResults' => $analytics['institutionResults'],
        ]);
    }

    public function statisticsExport(Request $request)
    {
        $user = $request->user();
        $service = app(StatisticsService::class);
        $period = $request->input('period', 'all');
        $institution = $request->input('institution', 'all');
        $category = $request->input('category', 'all');

        $query = $service->buildProblemQuery($user, $period, $institution, $category);
        $analytics = $service->getAnalytics($query);

        $institutionLabel = $institution && $institution !== 'all'
            ? (Institution::find($institution)?->name ?? $institution)
            : null;
        $categoryLabel = $category && $category !== 'all' ? $category : null;

        $filename = 'statistikos_ataskaita_'.date('Y-m-d_His').'.xlsx';

        return Excel::download(
            new StatisticsReportExport($analytics, $period, $institutionLabel, $categoryLabel),
            $filename
        );
    }
}

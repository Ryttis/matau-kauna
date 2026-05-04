<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Exports\StatisticsReportExport;
use App\Models\ClassifierValue;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ProblemDeadlineMonitorService;
use App\Services\ProblemScopeService;
use App\Services\StatisticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            return $this->renderResolutionTerms($request);
        }

        $service = app(StatisticsService::class);
        $period = $request->input('period', 'all');
        $institution = $request->input('institution', 'all');
        $category = $request->input('category', 'all');
        $institutionAssignment = $request->input('institution_assignment', 'all');
        if ($institutionAssignment === null || $institutionAssignment === '') {
            $institutionAssignment = 'all';
        }
        if (! in_array($institutionAssignment, ['all', 'assigned', 'unassigned'], true)) {
            $institutionAssignment = 'all';
        }

        $narrowPersonalScope = $this->scopeService->restrictsToOwnAssignmentsOnly($user);
        if ($narrowPersonalScope) {
            if ($user->institution_id !== null) {
                $institution = (string) $user->institution_id;
            } else {
                $eid = $this->scopeService->employeeIdForUser($user);
                if ($eid !== null) {
                    $iid = Employee::query()->whereKey($eid)->value('institution_id');
                    if ($iid !== null) {
                        $institution = (string) $iid;
                    }
                }
            }
        } elseif ($user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && $user->institution_id !== null) {
            if ($institution === 'all' || $institution === null || $institution === '') {
                $institution = (string) $user->institution_id;
            }
        }

        $institutions = $service->institutionsForUser($user)->map(fn ($i) => ['id' => $i->id, 'name' => $i->name])->values()->all();
        $categories = ClassifierValue::getActiveTypeNames()->values()->all();

        $query = $service->buildProblemQuery($user, $period, $institution, $category, $institutionAssignment);
        $analytics = $service->getAnalytics($query);

        return Inertia::render('Statistics', [
            'categories' => $categories,
            'institutions' => $institutions,
            'filters' => [
                'period' => $period,
                'institution' => $institution,
                'category' => $category,
                'institution_assignment' => $institutionAssignment,
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
            'statisticsFilterLocks' => [
                'institution' => $narrowPersonalScope
                    || ($user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && $user->institution_id !== null),
            ],
        ]);
    }

    public function statisticsExport(Request $request)
    {
        $user = $request->user();
        $service = app(StatisticsService::class);
        $period = $request->input('period', 'all');
        $institution = $request->input('institution', 'all');
        $category = $request->input('category', 'all');
        $institutionAssignment = $request->input('institution_assignment', 'all');
        if ($institutionAssignment === null || $institutionAssignment === '') {
            $institutionAssignment = 'all';
        }
        if (! in_array($institutionAssignment, ['all', 'assigned', 'unassigned'], true)) {
            $institutionAssignment = 'all';
        }

        $narrowPersonalScope = $this->scopeService->restrictsToOwnAssignmentsOnly($user);
        if ($narrowPersonalScope) {
            if ($user->institution_id !== null) {
                $institution = (string) $user->institution_id;
            } else {
                $eid = $this->scopeService->employeeIdForUser($user);
                if ($eid !== null) {
                    $iid = Employee::query()->whereKey($eid)->value('institution_id');
                    if ($iid !== null) {
                        $institution = (string) $iid;
                    }
                }
            }
        } elseif ($user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && $user->institution_id !== null) {
            if ($institution === 'all' || $institution === null || $institution === '') {
                $institution = (string) $user->institution_id;
            }
        }

        $query = $service->buildProblemQuery($user, $period, $institution, $category, $institutionAssignment);
        $analytics = $service->getAnalytics($query);

        $institutionLabel = $institution && $institution !== 'all'
            ? (Institution::find($institution)?->name ?? $institution)
            : null;
        $categoryLabel = $category && $category !== 'all' ? $category : null;

        $institutionAssignmentLabel = match ($institutionAssignment) {
            'assigned' => 'Tik su priskirta įstaiga',
            'unassigned' => 'Be įstaigos',
            default => 'Visi',
        };

        $filename = 'statistikos_ataskaita_'.date('Y-m-d_His').'.xlsx';

        return Excel::download(
            new StatisticsReportExport($analytics, $period, $institutionLabel, $categoryLabel, $institutionAssignmentLabel),
            $filename
        );
    }

    private function renderResolutionTerms(Request $request): Response
    {
        $user = $request->user();
        $statisticsService = app(StatisticsService::class);
        $institutions = $statisticsService->institutionsForUser($user);

        $institutionParam = $request->input('institution', 'all');
        $employeeParam = $request->input('employee', 'all');
        if ($institutionParam === null || $institutionParam === '') {
            $institutionParam = 'all';
        }
        if ($employeeParam === null || $employeeParam === '') {
            $employeeParam = 'all';
        }

        $narrowPersonalScope = $this->scopeService->restrictsToOwnAssignmentsOnly($user);
        $filterLocks = [
            'institution' => $narrowPersonalScope
                || ($user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && $user->institution_id !== null),
            'employee' => $narrowPersonalScope,
        ];

        if ($narrowPersonalScope) {
            $employeeId = $this->scopeService->employeeIdForUser($user);
            if (! $employeeId) {
                abort(403);
            }
            $employeeParam = (string) $employeeId;
            $employeeRow = Employee::query()->find($employeeId, ['id', 'institution_id']);
            $institutionParam = $employeeRow?->institution_id
                ? (string) $employeeRow->institution_id
                : ($user->institution_id !== null ? (string) $user->institution_id : 'all');
        } elseif ($user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && $user->institution_id !== null) {
            if ($institutionParam === 'all' || $institutionParam === '') {
                $institutionParam = (string) $user->institution_id;
            }
        }

        $allowedInstitutionIds = $institutions->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($institutionParam !== 'all' && ! in_array((int) $institutionParam, $allowedInstitutionIds, true)) {
            abort(403);
        }

        $employees = $this->resolutionTermsEmployeeOptions($user, $institutionParam)->values()->all();

        $allowedEmployeeIds = collect($employees)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($employeeParam !== 'all' && ! in_array((int) $employeeParam, $allowedEmployeeIds, true)) {
            $employeeParam = 'all';
        }

        if ($narrowPersonalScope) {
            $employeeParam = (string) $this->scopeService->employeeIdForUser($user);
        }

        $scopedBase = Problem::query()
            ->notArchived()
            ->whereNotNull('completion_deadline')
            ->whereNull('merged_into_problem_id')
            ->with(['institution:id,name', 'assignedEmployee:id,name,surname,email']);
        $this->scopeService->scopeProblemsForUser($scopedBase, $user);

        $dataQuery = clone $scopedBase;
        if ($institutionParam !== 'all') {
            $dataQuery->where('problems.institution_id', (int) $institutionParam);
        }
        if ($employeeParam !== 'all') {
            $dataQuery->where('problems.assigned_employee_id', (int) $employeeParam);
        }

        $all = $dataQuery->orderBy('completion_deadline')->get();
        $today = now()->startOfDay();
        $approachingEnd = $today->copy()->addDays(ProblemDeadlineMonitorService::REMIND_DAYS_BEFORE);
        $resolvedValue = ProblemStatus::RESOLVED->value;
        $approaching = $all->filter(fn ($problem) => $problem->completion_deadline && $problem->completion_deadline->gte($today) && $problem->completion_deadline->lte($approachingEnd) && $problem->status !== $resolvedValue);
        $overdue = $all->filter(fn ($problem) => $problem->completion_deadline && $problem->completion_deadline->lt($today) && $problem->status !== $resolvedValue);

        $resolvedWithDeadline = $all->where('status', $resolvedValue)->filter(fn ($p) => $p->completion_deadline !== null);
        $onTime = $resolvedWithDeadline->filter(function ($p) {
            if (! $p->status_updated_at || ! $p->completion_deadline) {
                return false;
            }

            return $p->status_updated_at->lte($p->completion_deadline->copy()->endOfDay());
        })->count();
        $late = $resolvedWithDeadline->count() - $onTime;

        $mapRow = fn ($problem) => [
            'id' => $problem->id,
            'internal_number' => $problem->internal_number,
            'description_preview' => Problem::descriptionPreview($problem->description, 120),
            'status' => $problem->status,
            'completion_deadline' => $problem->completion_deadline?->format('Y-m-d'),
            'institution_name' => $problem->institution?->name,
            'assigned_to' => $problem->assignedEmployee ? trim($problem->assignedEmployee->name.' '.$problem->assignedEmployee->surname) : null,
        ];

        return Inertia::render('Statistics/ResolutionTerms', [
            'approaching' => $approaching->values()->map($mapRow)->all(),
            'overdue' => $overdue->values()->map($mapRow)->all(),
            'institutions' => $institutions->map(fn ($i) => ['id' => $i->id, 'name' => $i->name])->values()->all(),
            'employees' => $employees,
            'filters' => [
                'institution' => $institutionParam,
                'employee' => $employeeParam,
            ],
            'filterLocks' => $filterLocks,
            'deadlineResolutionStats' => [
                'on_time' => $onTime,
                'late' => max(0, $late),
            ],
        ]);
    }

    private function resolutionTermsEmployeeOptions(User $user, string $institutionParam): Collection
    {
        if ($this->scopeService->restrictsToOwnAssignmentsOnly($user)) {
            $eid = $this->scopeService->employeeIdForUser($user);
            if (! $eid) {
                return collect();
            }
            $row = Employee::query()->find($eid, ['id', 'name', 'surname', 'institution_id']);
            if (! $row) {
                return collect();
            }

            return collect([[
                'id' => $row->id,
                'label' => trim($row->name.' '.$row->surname),
                'institution_id' => $row->institution_id,
            ]]);
        }

        $q = Employee::query()
            ->whereHas('institution', fn (Builder $b) => $b->notArchived());

        if ($user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)) {
            if ($user->institution_id === null) {
                return collect();
            }
            $q->where('institution_id', $user->institution_id);
        } elseif ($institutionParam !== 'all') {
            $q->where('institution_id', (int) $institutionParam);
        }

        return $q->orderBy('surname')
            ->orderBy('name')
            ->get(['id', 'name', 'surname', 'institution_id'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'label' => trim($e->name.' '.$e->surname),
                'institution_id' => $e->institution_id,
            ]);
    }
}

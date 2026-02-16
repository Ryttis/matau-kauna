<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StatisticsService
{
    public function institutionsForUser(?User $user): Collection
    {
        $query = Institution::query()->notArchived()->orderBy('name');

        if ($user && $this->isInstitutionScoped($user)) {
            $query->where('id', $user->institution_id);
        }

        return $query->get(['id', 'name']);
    }

    public function buildPublicProblemQuery(?string $period, ?string $categoryName): Builder
    {
        $query = Problem::query()
            ->notArchived()
            ->whereNull('merged_into_problem_id')
            ->where('is_published_publicly', true);

        $this->applyPeriodFilter($query, $period);
        if ($categoryName && $categoryName !== 'all') {
            $query->whereHas('problemType', fn (Builder $q) => $q->where('name', $categoryName));
        }

        return $query;
    }

    public function buildProblemQuery(?User $user, ?string $period, $institutionId, ?string $categoryName): Builder
    {
        $query = Problem::query()
            ->notArchived()
            ->whereNull('merged_into_problem_id');

        $this->applyUserScope($query, $user);
        $this->applyPeriodFilter($query, $period);

        if ($institutionId && $institutionId !== 'all') {
            $query->where('institution_id', (int) $institutionId);
        }

        if ($categoryName && $categoryName !== 'all') {
            $query->whereHas('problemType', fn (Builder $q) => $q->where('name', $categoryName));
        }

        return $query;
    }

    private function applyPeriodFilter(Builder $query, ?string $period): void
    {
        if (! $period || $period === 'all') {
            return;
        }
        $from = match ($period) {
            'week' => Carbon::now()->subDays(7)->startOfDay(),
            'month' => Carbon::now()->subMonth()->startOfDay(),
            'quarter' => Carbon::now()->subMonths(3)->startOfDay(),
            'year' => Carbon::now()->subYear()->startOfDay(),
            default => null,
        };
        if ($from) {
            $query->where('problems.created_at', '>=', $from);
        }
    }

    public function getAnalytics(Builder $baseQuery): array
    {
        $problems = (clone $baseQuery)->with(['problemType', 'institution', 'assignedEmployee'])->get();

        $receivedReports = $problems->count();
        $resolved = $problems->where('status', 'isspresta');
        $resolvedCount = $resolved->count();
        $resolvedWithDeadline = $resolved->filter(fn ($p) => $p->completion_deadline !== null);
        $onTime = $resolvedWithDeadline->filter(fn ($p) => $p->status_updated_at && $p->status_updated_at->lte($p->completion_deadline->endOfDay()))->count();
        $resolvedOnTimePct = $resolvedWithDeadline->isEmpty() ? 0 : round($onTime / $resolvedWithDeadline->count() * 100);
        $resolvedPct = $receivedReports === 0 ? 0 : round($resolvedCount / $receivedReports * 100);

        $resolutionDays = $resolved->filter(function ($p) {
            return $p->created_at && $p->status_updated_at;
        })->map(fn ($p) => $p->created_at->diffInDays($p->status_updated_at));
        $averageResolutionTime = $resolutionDays->isEmpty() ? 0 : (int) round($resolutionDays->avg());

        $activeReportersInPeriod = (clone $baseQuery)->whereNotNull('reported_by_user_id')->distinct('reported_by_user_id')->pluck('reported_by_user_id')->count();
        $totalReportersEver = Problem::query()
            ->notArchived()
            ->whereNull('merged_into_problem_id')
            ->whereNotNull('reported_by_user_id');
        $this->applyUserScope($totalReportersEver, request()->user());
        $totalReportersEver = $totalReportersEver->distinct('reported_by_user_id')->pluck('reported_by_user_id')->count();
        $activeUsers = ['current' => $activeReportersInPeriod, 'total' => max($totalReportersEver, 1)];

        $monthlyReports = $this->monthlyCounts(clone $baseQuery);
        $problematicCategories = $this->problematicCategories(clone $baseQuery);
        $effectivelyResolved = $this->effectivelyResolvedCategories(clone $baseQuery);
        $employeeActivity = $this->employeeActivity(clone $baseQuery);
        $institutionResults = $this->institutionResults(clone $baseQuery);

        $previousPeriodResolved = null;
        $improvement = 0;

        return [
            'activeUsers' => $activeUsers,
            'receivedReports' => $receivedReports,
            'averageResolutionTime' => $averageResolutionTime,
            'resolvedProblems' => $resolvedPct,
            'resolvedOnTime' => $resolvedOnTimePct,
            'improvement' => $improvement,
            'monthlyReports' => $monthlyReports,
            'problematicCategories' => $problematicCategories,
            'effectivelyResolved' => $effectivelyResolved,
            'employeeActivity' => $employeeActivity,
            'institutionResults' => $institutionResults,
        ];
    }

    private function applyUserScope(Builder $query, ?User $user): void
    {
        if (! $user) {
            return;
        }
        if ($this->isInstitutionScoped($user)) {
            $query->where('institution_id', $user->institution_id);
            return;
        }
        if ($this->isEmployeeScoped($user)) {
            $employeeId = $this->employeeIdForUser($user);
            if ($employeeId) {
                $query->where('assigned_employee_id', $employeeId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
    }

    private function isInstitutionScoped(?User $user): bool
    {
        return $user && (
            $user->hasRole(\App\Models\UserRole::ROLE_INSTITUTION_ADMIN)
            || $user->hasRole(\App\Models\UserRole::ROLE_EMPLOYEE)
            || $user->hasRole(\App\Models\UserRole::ROLE_MUNICIPALITY_REP)
        );
    }

    private function isEmployeeScoped(?User $user): bool
    {
        return $user && $user->hasRole(\App\Models\UserRole::ROLE_EMPLOYEE);
    }

    private function employeeIdForUser(?User $user): ?int
    {
        if (! $user) {
            return null;
        }
        $query = \App\Models\Employee::where('email', $user->email);
        if ($user->institution_id !== null) {
            $query->where('institution_id', $user->institution_id);

            return $query->value('id');
        }
        $employees = $query->get(['id']);
        if ($employees->count() === 1) {
            return $employees->first()->id;
        }

        return null;
    }

    private function monthlyCounts(Builder $query): array
    {
        $end = Carbon::now()->endOfMonth();
        $start = Carbon::now()->subMonths(11)->startOfMonth();
        $base = (clone $query)->whereBetween('problems.created_at', [$start, $end]);

        $rows = $base
            ->selectRaw("date_trunc('month', problems.created_at) as month, count(*) as cnt")
            ->groupByRaw("date_trunc('month', problems.created_at)")
            ->get();

        $counts = [];
        foreach ($rows as $r) {
            $ym = Carbon::parse($r->month)->format('Y-m');
            $counts[$ym] = (int) $r->cnt;
        }

        $result = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = Carbon::now()->subMonths($i);
            $ym = $m->format('Y-m');
            $result[] = [
                'label' => $m->translatedFormat('Y-m'),
                'value' => $counts[$ym] ?? 0,
            ];
        }

        return $result;
    }

    private function problematicCategories(Builder $query): array
    {
        $items = (clone $query)
            ->join('classifier_values as cv', 'problems.problem_type_id', '=', 'cv.id')
            ->selectRaw('cv.name as name, count(*) as count')
            ->groupBy('cv.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count])
            ->all();

        return $items;
    }

    private function effectivelyResolvedCategories(Builder $query): array
    {
        $base = (clone $query)->where('problems.status', 'isspresta');
        $items = $base
            ->join('classifier_values as cv', 'problems.problem_type_id', '=', 'cv.id')
            ->selectRaw('cv.name as name, count(*) as count')
            ->groupBy('cv.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count])
            ->all();

        return $items;
    }

    private function employeeActivity(Builder $query): array
    {
        $items = (clone $query)
            ->whereNotNull('problems.assigned_employee_id')
            ->join('employees', 'problems.assigned_employee_id', '=', 'employees.id')
            ->selectRaw("trim(concat(coalesce(employees.name,''), ' ', coalesce(employees.surname,''))) as name, count(*) as count")
            ->groupBy('employees.id', 'employees.name', 'employees.surname')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count])
            ->all();

        return $items;
    }

    private function institutionResults(Builder $query): array
    {
        $items = (clone $query)
            ->whereNotNull('problems.institution_id')
            ->join('institutions', 'problems.institution_id', '=', 'institutions.id')
            ->selectRaw('institutions.id as institution_id, institutions.name as name, count(*) as total')
            ->groupBy('institutions.id', 'institutions.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $resolved = (clone $query)
            ->where('problems.status', 'isspresta')
            ->whereNotNull('problems.institution_id')
            ->selectRaw('problems.institution_id, count(*) as resolved')
            ->groupBy('problems.institution_id')
            ->pluck('resolved', 'institution_id');

        return $items->map(fn ($r) => [
            'name' => $r->name,
            'total' => (int) $r->total,
            'resolved' => (int) ($resolved[$r->institution_id ?? null] ?? 0),
        ])->values()->all();
    }
}

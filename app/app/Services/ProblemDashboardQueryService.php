<?php

namespace App\Services;

use App\Enums\ProblemStatus;
use App\Models\ClassifierValue;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProblemDashboardQueryService
{
    public const STAFF_PAGE_SIZES = [10, 20, 50, 100];

    public function __construct(
        private ProblemScopeService $scopeService,
        private ProblemMapService $mapService,
    ) {}

    public function reporterDashboardProps(User $user, bool $onlyMine, bool $onlyWatched = false): array
    {
        return [
            'reporterView' => true,
            'onlyMine' => $onlyMine,
            'onlyWatched' => $onlyWatched,
            'problems' => $this->mapService->getPublicProblemsForMap(
                $onlyMine ? $user->id : null,
                $user,
                ! $onlyMine,
            ),
        ];
    }

    public function paginatedStaffProblems(User $user, array $filters, string $sortBy, string $sortDirection, ?int $perPage = null): LengthAwarePaginator
    {
        $pageSize = $this->normalizeStaffPerPage($perPage);

        return $this->staffProblemsBaseQuery($user, $filters)
            ->sorted($sortBy, $sortDirection)
            ->with([
                'mergedProblems' => fn ($query) => $query->with([
                    'problemNature:id,name',
                    'reportedByUser:id,name,surname,email',
                    'institution:id,name',
                    'assignedEmployee:id,name,surname,email',
                    'assignedInstitutionTeam:id,name',
                ]),
                'problemNature:id,name',
                'institution:id,name',
                'reportedByUser:id,name,surname,email',
                'assignedEmployee:id,name,surname,email',
                'assignedInstitutionTeam:id,name',
            ])
            ->paginate($pageSize)
            ->withQueryString();
    }

    public function availableStaffStatusCounts(User $user, array $filters): array
    {
        $countFilters = $filters;
        unset($countFilters['status'], $countFilters['statuses']);

        return $this->staffProblemsBaseQuery($user, $countFilters)
            ->selectRaw('status, COUNT(*) as problem_count')
            ->groupBy('status')
            ->pluck('problem_count', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function staffDashboardProps(User $user, array $filters, string $sortBy, string $sortDirection, ?int $perPage = null): array
    {
        return [
            'reporterView' => false,
            'problems' => $this->paginatedStaffProblems($user, $filters, $sortBy, $sortDirection, $perPage),
            'filters' => $filters,
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection,
            'categories' => ClassifierValue::getActiveTypeNames()->values(),
            'natures' => $this->activeNatureOptions(),
            'institutions' => $this->institutionOptionsFor($user),
            'statusOptions' => ProblemStatus::options(),
            'availableStatusCounts' => $this->availableStaffStatusCounts($user, $filters),
            'pageSizeOptions' => self::STAFF_PAGE_SIZES,
        ];
    }

    public function problemsForExport(User $user, array $filters, string $sortBy, string $sortDirection, string $scope, int $page, ?int $perPage = null): Collection
    {
        $query = Problem::query()
            ->notArchived()
            ->with([
                'institution:id,name',
                'assignedEmployee:id,name,surname,email',
                'assignedInstitutionTeam:id,name',
            ])
            ->when(
                $this->scopeService->isInstitutionScoped($user),
                fn ($q) => $q->where('institution_id', $user->institution_id)
            )
            ->filtered($filters, true)
            ->sorted($sortBy, $sortDirection);

        if ($scope === 'current_page') {
            $perPage = $this->normalizeStaffPerPage($perPage);

            return $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        }

        return $query->get();
    }

    public function normalizeStaffPerPage(?int $perPage): int
    {
        return in_array($perPage, self::STAFF_PAGE_SIZES, true) ? $perPage : self::STAFF_PAGE_SIZES[0];
    }

    private function staffProblemsBaseQuery(User $user, array $filters): Builder
    {
        $employeeId = $this->scopeService->employeeIdForUser($user);
        $mergeRootIdsFromChildMatch = Problem::mergeGroupRootIdsFromMatchingMergedChildren($filters, true);

        return Problem::query()
            ->whereNull('merged_into_problem_id')
            ->notArchived()
            ->when(
                $this->scopeService->isInstitutionScoped($user),
                function ($query) use ($user) {
                    if ($user->institution_id === null) {
                        $query->whereRaw('1 = 0');
                    } else {
                        $query->where('institution_id', $user->institution_id);
                    }
                }
            )
            ->when(
                $this->scopeService->isEmployeeScoped($user),
                function ($query) use ($employeeId) {
                    if ($employeeId) {
                        $query->whereVisibleToEmployee($employeeId);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                }
            )
            ->where(function ($query) use ($filters, $mergeRootIdsFromChildMatch) {
                $query->where(function ($inner) use ($filters) {
                    $inner->filtered($filters, true);
                });
                if ($mergeRootIdsFromChildMatch !== []) {
                    $query->orWhereIn('id', $mergeRootIdsFromChildMatch);
                }
            });
    }

    private function activeNatureOptions(): array
    {
        return ClassifierValue::query()
            ->notArchived()
            ->where('is_active', true)
            ->whereNotNull('parent_id')
            ->whereHas('parent', fn ($query) => $query->notArchived()->where('is_active', true))
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name'])
            ->map(fn (ClassifierValue $nature) => [
                'id' => $nature->id,
                'name' => $nature->name,
                'category' => $nature->parent?->name,
            ])
            ->values()
            ->all();
    }

    private function institutionOptionsFor(User $user): array
    {
        $query = Institution::query()
            ->notArchived()
            ->orderBy('name')
            ->select(['id', 'name']);

        if ($this->scopeService->isInstitutionScoped($user)) {
            if ($user->institution_id === null) {
                return [];
            }

            $query->where('id', $user->institution_id);
        }

        return $query
            ->get()
            ->map(fn (Institution $institution) => [
                'id' => $institution->id,
                'name' => $institution->name,
            ])
            ->values()
            ->all();
    }
}

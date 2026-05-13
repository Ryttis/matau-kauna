<?php

namespace App\Services;

use App\Enums\ProblemStatus;
use App\Models\ClassifierValue;
use App\Models\Problem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProblemDashboardQueryService
{
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
        $pageSize = $perPage === null ? 6 : min(max($perPage, 1), 100);
        $employeeId = $this->scopeService->employeeIdForUser($user);
        $mergeRootIdsFromChildMatch = Problem::mergeGroupRootIdsFromMatchingMergedChildren($filters);

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
                    $inner->filtered($filters);
                });
                if ($mergeRootIdsFromChildMatch !== []) {
                    $query->orWhereIn('id', $mergeRootIdsFromChildMatch);
                }
            })
            ->sorted($sortBy, $sortDirection)
            ->with(['mergedProblems', 'problemNature:id,name', 'institution:id,name'])
            ->paginate($pageSize)
            ->withQueryString();
    }

    public function staffDashboardProps(User $user, array $filters, string $sortBy, string $sortDirection): array
    {
        return [
            'reporterView' => false,
            'problems' => $this->paginatedStaffProblems($user, $filters, $sortBy, $sortDirection),
            'filters' => $filters,
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection,
            'categories' => ClassifierValue::getActiveTypeNames()->values(),
            'statusOptions' => ProblemStatus::selectableOptions(),
        ];
    }

    public function problemsForExport(User $user, array $filters, string $sortBy, string $sortDirection, string $scope, int $page): Collection
    {
        $query = Problem::query()
            ->notArchived()
            ->when(
                $this->scopeService->isInstitutionScoped($user),
                fn ($q) => $q->where('institution_id', $user->institution_id)
            )
            ->filtered($filters)
            ->sorted($sortBy, $sortDirection);

        if ($scope === 'current_page') {
            $perPage = 6;

            return $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        }

        return $query->get();
    }
}

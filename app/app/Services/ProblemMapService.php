<?php

namespace App\Services;

use App\Models\Problem;
use App\Models\User;
use Illuminate\Support\Collection;

class ProblemMapService
{
    public function __construct(
        private ProblemScopeService $scopeService
    ) {}

    public function getProblemsForMapForUser(?User $user): array
    {
        $center = config('kaunas.map_center');
        $centerLat = $center['lat'];
        $centerLng = $center['lng'];

        $query = Problem::query()
            ->whereIn('status', ['nauja', 'nagrinejama', 'isspresta'])
            ->when(
                $user && $this->scopeService->isInstitutionScoped($user),
                fn ($q) => $q->where('institution_id', $user->institution_id)
            )
            ->when(
                $user && $this->scopeService->isEmployeeScoped($user),
                function ($q) use ($user) {
                    $employeeId = $this->scopeService->employeeIdForUser($user);
                    if ($employeeId) {
                        $q->where('assigned_employee_id', $employeeId);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                }
            );

        return $query
            ->orderBy('address')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'title', 'status', 'category', 'address', 'lat', 'lng', 'created_at'])
            ->map(function ($problem, $index) use ($centerLat, $centerLng) {
                return $this->mapProblemToMapItem($problem, $centerLat, $centerLng, $index);
            })
            ->values()
            ->all();
    }

    public function getPublicProblemsForMap(?int $reportedByUserId = null): array
    {
        $center = config('kaunas.map_center');
        $centerLat = $center['lat'];
        $centerLng = $center['lng'];
        $allowedStatuses = ['nauja', 'nagrinejama', 'isspresta'];

        $query = Problem::query()
            ->whereIn('status', $allowedStatuses)
            ->when($reportedByUserId === null, fn ($q) => $q->where('is_published_publicly', true))
            ->when($reportedByUserId !== null, function ($q) use ($reportedByUserId) {
                $q->where(function ($q2) use ($reportedByUserId) {
                    $q2->where('reported_by_user_id', $reportedByUserId)
                        ->orWhereHas('mergedInto', fn ($q3) => $q3->where('reported_by_user_id', $reportedByUserId));
                });
            })
            ->orderBy('address')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'title', 'status', 'category', 'address', 'lat', 'lng', 'created_at', 'merged_into_problem_id', 'is_published_publicly']);

        $index = 0;

        return $query->map(function ($problem) use ($centerLat, $centerLng, &$index) {
            $item = $this->mapProblemToMapItem($problem, $centerLat, $centerLng, $index);
            $index++;
            if (isset($problem->merged_into_problem_id)) {
                $item['merged_into_problem_id'] = $problem->merged_into_problem_id;
            }

            return $item;
        })->values()->all();
    }

    public function buildMapProblemsForMerge(Problem $problem, Collection $mergedProblems, Collection $similarProblems): array
    {
        $center = config('kaunas.map_center');
        $centerLat = $center['lat'];
        $centerLng = $center['lng'];
        $problemsForMap = collect([$problem])->concat($mergedProblems);
        foreach ($similarProblems as $s) {
            $problemsForMap->push($s);
            $problemsForMap = $problemsForMap->merge($s->mergedProblems ?? collect());
        }
        $mapProblems = [];
        $index = 0;
        foreach ($problemsForMap as $p) {
            $mapProblems[] = $this->mapProblemToMapItem($p, $centerLat, $centerLng, $index);
            $index++;
        }

        return $mapProblems;
    }

    private function mapProblemToMapItem(object $problem, float $centerLat, float $centerLng, int $index): array
    {
        $hasCoords = $problem->lat !== null && $problem->lng !== null;
        $offset = $index * 0.002;

        return [
            'id' => $problem->id,
            'internal_number' => $problem->internal_number,
            'title' => $problem->title,
            'status' => $problem->status ?? 'nauja',
            'category' => $problem->category,
            'address' => $problem->address,
            'created_at' => $problem->created_at?->toIso8601String(),
            'lat' => $hasCoords ? (float) $problem->lat : $centerLat + ($offset * 0.1),
            'lng' => $hasCoords ? (float) $problem->lng : $centerLng + ($offset * 0.1),
        ];
    }
}

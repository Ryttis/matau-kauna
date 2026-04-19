<?php

namespace App\Services;

use App\Enums\ProblemStatus;
use App\Models\Problem;
use App\Support\LithuanianSearchFold;
use App\Models\ProblemWatch;
use App\Models\User;
use Illuminate\Support\Collection;

class ProblemMapService
{
    public function __construct(
        private ProblemScopeService $scopeService
    ) {}

    public function getProblemsForMapForUser(?User $user): array
    {
        return $this->getMapQueryForUser($user)
            ->orderBy('address')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'description', 'status', 'category', 'address', 'lat', 'lng', 'created_at'])
            ->map(function ($problem, $index) {
                return $this->mapProblemToMapItem($problem, $this->getCenterLat(), $this->getCenterLng(), $index);
            })
            ->values()
            ->all();
    }

    public function searchByAddress(?User $user, string $query, string $mode = 'all'): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }
        $mode = in_array($mode, ['number', 'address'], true) ? $mode : 'all';
        $patternNumber = $this->likePattern($q, false);
        $patternAddress = $this->likePattern($q, true);
        $addressNormSql = $this->sqlAddressDiacriticInsensitiveLower();
        $centerLat = $this->getCenterLat();
        $centerLng = $this->getCenterLng();
        $problems = $this->getMapQueryForUser($user)
            ->where(function ($sub) use ($patternNumber, $patternAddress, $addressNormSql, $mode) {
                if ($mode === 'number') {
                    $sub->whereRaw('LOWER(internal_number) LIKE ?', [$patternNumber]);

                    return;
                }
                if ($mode === 'address') {
                    $sub->whereNotNull('address')
                        ->where('address', '!=', '')
                        ->whereRaw("{$addressNormSql} LIKE ?", [$patternAddress]);

                    return;
                }
                $sub->where(function ($byAddress) use ($patternAddress, $addressNormSql) {
                    $byAddress->whereNotNull('address')
                        ->where('address', '!=', '')
                        ->whereRaw("{$addressNormSql} LIKE ?", [$patternAddress]);
                })->orWhereRaw('LOWER(internal_number) LIKE ?', [$patternNumber]);
            })
            ->orderBy('internal_number')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get(['id', 'internal_number', 'description', 'status', 'category', 'address', 'lat', 'lng', 'created_at']);

        return $problems->map(function ($problem, $index) use ($centerLat, $centerLng) {
            return $this->mapProblemToMapItem($problem, $centerLat, $centerLng, $index);
        })->values()->all();
    }

    private function likePattern(string $trimmedQuery, bool $foldLithuanianDiacritics): string
    {
        $lower = $foldLithuanianDiacritics
            ? LithuanianSearchFold::fold($trimmedQuery)
            : mb_strtolower($trimmedQuery, 'UTF-8');
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $lower);

        return '%'.$escaped.'%';
    }

    private function sqlAddressDiacriticInsensitiveLower(): string
    {
        return LithuanianSearchFold::sqlLowerAndFoldColumn('address');
    }

    private function getCenterLat(): float
    {
        return (float) config('kaunas.map_center.lat');
    }

    private function getCenterLng(): float
    {
        return (float) config('kaunas.map_center.lng');
    }

    private function getMapQueryForUser(?User $user): \Illuminate\Database\Eloquent\Builder
    {
        return Problem::query()
            ->notArchived()
            ->whereIn('status', ProblemStatus::staffMapStatusValues())
            ->when(
                $user && $this->scopeService->isInstitutionScoped($user),
                fn ($q) => $q->where('institution_id', $user->institution_id)
            )
            ->when(
                $user && $this->scopeService->isEmployeeScoped($user),
                function ($q) use ($user) {
                    $employeeId = $this->scopeService->employeeIdForUser($user);
                    if ($employeeId) {
                        $q->whereVisibleToEmployee($employeeId);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                }
            );
    }

    public function getPublicProblemsForMap(?int $reportedByUserId = null, ?User $currentUser = null): array
    {
        $centerLat = $this->getCenterLat();
        $centerLng = $this->getCenterLng();
        $statusFilter = ($currentUser !== null && $currentUser->isReporterOnly())
            ? ProblemStatus::reporterDashboardMapStatusValues()
            : ProblemStatus::publicMapStatusValues();
        $query = Problem::query()
            ->notArchived()
            ->whereIn('status', $statusFilter)
            ->when($reportedByUserId === null, fn ($q) => $q->where('is_published_publicly', true))
            ->when($reportedByUserId !== null, function ($q) use ($reportedByUserId) {
                $q->where(function ($q2) use ($reportedByUserId) {
                    $q2->where('reported_by_user_id', $reportedByUserId)
                        ->orWhereHas('mergedInto', fn ($q3) => $q3->where('reported_by_user_id', $reportedByUserId));
                });
            })
            ->orderBy('address')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'description', 'status', 'category', 'address', 'lat', 'lng', 'created_at', 'merged_into_problem_id', 'is_published_publicly']);

        $index = 0;

        $ids = $query->pluck('id')->all();
        $watchedSet = [];
        if ($currentUser !== null && $ids !== []) {
            $watchedSet = ProblemWatch::query()
                ->where('user_id', $currentUser->id)
                ->whereIn('problem_id', $ids)
                ->pluck('problem_id')
                ->flip()
                ->all();
        }

        return $query->map(function ($problem) use ($centerLat, $centerLng, &$index, $watchedSet) {
            $item = $this->mapProblemToMapItem($problem, $centerLat, $centerLng, $index);
            $index++;
            if ($problem->merged_into_problem_id !== null) {
                $item['merged_into_problem_id'] = $problem->merged_into_problem_id;
            }
            $watchRootId = $problem->merged_into_problem_id ?? $problem->id;
            $item['is_watched'] = isset($watchedSet[$watchRootId]);

            return $item;
        })->values()->all();
    }

    public function buildMapProblemsForMerge(Problem $problem, Collection $mergedProblems, Collection $similarProblems): array
    {
        $centerLat = $this->getCenterLat();
        $centerLng = $this->getCenterLng();
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

        $description = (string) ($problem->description ?? '');

        return [
            'id' => $problem->id,
            'internal_number' => $problem->internal_number,
            'description_preview' => Problem::descriptionPreview($description, 200),
            'description' => $description,
            'status' => $problem->status ?? ProblemStatus::NEW->value,
            'category' => $problem->category,
            'address' => $problem->address,
            'created_at' => $problem->created_at?->toIso8601String(),
            'lat' => $hasCoords ? (float) $problem->lat : $centerLat + ($offset * 0.1),
            'lng' => $hasCoords ? (float) $problem->lng : $centerLng + ($offset * 0.1),
        ];
    }
}

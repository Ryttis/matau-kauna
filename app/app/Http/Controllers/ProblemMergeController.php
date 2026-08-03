<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Models\ClassifierValue;
use App\Models\Problem;
use App\Services\ProblemMapService;
use App\Services\ProblemMergeGroupSyncService;
use App\Services\ProblemScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProblemMergeController extends Controller
{
    public function __construct(
        private ProblemScopeService $scopeService,
        private ProblemMapService $mapService,
        private ProblemMergeGroupSyncService $mergeGroupSyncService,
    ) {}

    public function merge(Request $request, Problem $problem): Response|RedirectResponse
    {
        $user = $request->user();
        $this->scopeService->authorizeMergeAccess($user, $problem);
        if ($problem->is_archived) {
            abort(404);
        }
        if ($this->scopeService->isEmployeeScoped($user)) {
            $employeeId = $this->scopeService->employeeIdForUser($user);
            if (! $this->scopeService->employeeHasAccessToAssignment($employeeId, $problem)) {
                abort(403);
            }
        }

        if ($problem->merged_into_problem_id !== null) {
            $problem->load('mergedInto:id');
            if ($problem->mergedInto) {
                return redirect()->route('problems.merge', $problem->mergedInto->id);
            }
        }

        $categoryFilter = $request->get('category');

        if ($categoryFilter === null || $categoryFilter === '') {
            $categoryFilter = '';
        }

        $categories = ClassifierValue::getActiveTypeNames()->values();

        $query = Problem::where('id', '!=', $problem->id)
            ->whereNull('merged_into_problem_id')
            ->where('status', '!=', ProblemStatus::RESOLVED->value)
            ->whereDoesntHave('mergedProblems', fn ($q) => $q->where('status', ProblemStatus::RESOLVED->value))
            ->notArchived();
        if ($this->scopeService->isInstitutionScoped($user)) {
            $query->where('institution_id', $user->institution_id);
        }
        if ($this->scopeService->isEmployeeScoped($user)) {
            $employeeId = $this->scopeService->employeeIdForUser($user);
            if ($employeeId) {
                $query->whereVisibleToEmployee($employeeId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($categoryFilter !== '') {
            $query->where('category', $categoryFilter);
        }

        $similarProblems = $query->with([
            'mergedProblems' => fn ($q) => $q->notArchived()
                ->where('status', '!=', ProblemStatus::RESOLVED->value)
                ->select(['id', 'internal_number', 'description', 'merged_into_problem_id', 'lat', 'lng', 'address', 'category', 'status', 'created_at']),
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        $problem->load([
            'mergedProblems' => fn ($q) => $q->notArchived()->with([
                'mergedProblems' => fn ($q2) => $q2->notArchived(),
            ]),
        ]);
        $directMerged = $problem->mergedProblems;
        $mergedProblems = $directMerged->flatMap(function ($merged) {
            return collect([$merged])->merge($merged->mergedProblems ?? collect());
        })->values();

        $mapProblems = $this->mapService->buildMapProblemsForMerge($problem, $mergedProblems, $similarProblems);

        return Inertia::render('Problems/Merge', [
            'problem' => $problem,
            'mergedProblems' => $mergedProblems,
            'similarProblems' => $similarProblems,
            'categories' => $categories,
            'selectedCategory' => $categoryFilter,
            'mapProblems' => $mapProblems,
        ]);
    }

    public function storeMerge(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        $this->scopeService->authorizeMergeAccess($user, $problem);
        if ($problem->is_archived) {
            return redirect()->route('dashboard')->with('error', 'Archyvuotos problemos apjungti negalima.');
        }

        $validated = $request->validate([
            'problem_ids' => 'required|array',
            'problem_ids.*' => 'required|integer|exists:problems,id',
        ]);

        $selectedIds = array_values(array_filter(
            array_unique(array_map('intval', $validated['problem_ids'])),
            fn (int $id) => $id !== (int) $problem->id
        ));

        if ($selectedIds === []) {
            return redirect()->back()->with('info', 'Nėra ką apjungti.');
        }

        // Į grupę patenka ir dabartinė problema - ji gali tapti vaiku, jei tarp
        // pažymėtų problemų yra jau egzistuojanti grupės šaknis.
        $groupIds = $this->collectGroupIds(array_merge([(int) $problem->id], $selectedIds));

        if (Problem::query()->whereIn('id', $groupIds)->where('is_archived', true)->exists()) {
            return redirect()->back()->with('error', 'Negalima apjungti archyvuotų problemų.');
        }

        if (Problem::query()->whereIn('id', $groupIds)->where('status', ProblemStatus::RESOLVED->value)->exists()) {
            return redirect()->back()->with('error', 'Negalima apjungti išspręstų problemų.');
        }

        $scopeQuery = Problem::query()->whereIn('id', $groupIds);
        $this->scopeService->scopeProblemsForUser($scopeQuery, $user);
        $this->scopeService->ensureScopeMatchesCount($scopeQuery, count($groupIds));

        $rootId = $this->mergeGroupSyncService->resolveMergeRootId($problem, $selectedIds);

        $root = DB::transaction(function () use ($groupIds, $rootId) {
            Problem::query()
                ->whereIn('id', $groupIds)
                ->where('id', '!=', $rootId)
                ->update(['merged_into_problem_id' => $rootId]);

            Problem::query()
                ->where('id', $rootId)
                ->whereNotNull('merged_into_problem_id')
                ->update(['merged_into_problem_id' => null]);

            $root = Problem::query()->findOrFail($rootId);
            $this->mergeGroupSyncService->syncGroupFromRoot($root);

            return $root;
        });

        return redirect()
            ->route('problems.show', $root)
            ->with('success', $this->mergeSuccessMessage($problem, $root));
    }

    /**
     * Surenka pateiktas problemas kartu su visais jų palikuonimis.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function collectGroupIds(array $ids): array
    {
        $collected = [];
        $collectDescendants = function (int $problemId) use (&$collectDescendants, &$collected): void {
            if (isset($collected[$problemId])) {
                return;
            }
            $collected[$problemId] = true;
            $childIds = Problem::query()
                ->where('merged_into_problem_id', $problemId)
                ->pluck('id');
            foreach ($childIds as $childId) {
                $collectDescendants((int) $childId);
            }
        };

        foreach ($ids as $id) {
            $collectDescendants((int) $id);
        }

        return array_keys($collected);
    }

    private function mergeSuccessMessage(Problem $current, Problem $root): string
    {
        if ((int) $current->id === (int) $root->id) {
            return 'Problemos apjungtos.';
        }

        return 'Problemos apjungtos. Pagrindine problema liko '.$root->internal_number.'.';
    }

    public function unmerge(Request $request, Problem $problem): RedirectResponse
    {
        $this->scopeService->authorizeMergeAccess($request->user(), $problem);
        if ($problem->merged_into_problem_id === null) {
            return redirect()->back()->with('info', 'Problema neapjungta.');
        }

        $problem->update(['merged_into_problem_id' => null]);

        return redirect()->back()->with('success', 'Problema atskirta.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ClassifierValue;
use App\Models\Problem;
use App\Services\ProblemMapService;
use App\Services\ProblemScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProblemMergeController extends Controller
{
    public function __construct(
        private ProblemScopeService $scopeService,
        private ProblemMapService $mapService,
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
            ->where('status', '!=', 'neuzregistruota')
            ->whereNull('merged_into_problem_id')
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
                ->select(['id', 'internal_number', 'description', 'merged_into_problem_id', 'lat', 'lng', 'address']),
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

        $ids = array_unique($validated['problem_ids']);
        $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) $problem->id));

        $idsToMerge = [];
        $collectDescendants = function ($problemId) use (&$collectDescendants, &$idsToMerge) {
            $idsToMerge[(int) $problemId] = true;
            $sub = Problem::where('id', $problemId)->with('mergedProblems')->first();
            if ($sub && $sub->mergedProblems->isNotEmpty()) {
                foreach ($sub->mergedProblems as $m) {
                    $collectDescendants($m->id);
                }
            }
        };
        foreach ($ids as $id) {
            $collectDescendants($id);
        }
        $idsToMerge = array_keys($idsToMerge);

        if (Problem::query()->whereIn('id', $idsToMerge)->where('is_archived', true)->exists()) {
            return redirect()->back()->with('error', 'Negalima apjungti archyvuotų problemų.');
        }

        $query = Problem::whereIn('id', $idsToMerge);
        if ($this->scopeService->isInstitutionScoped($user)) {
            $query->where('institution_id', $user->institution_id);
        }
        $query->update(['merged_into_problem_id' => $problem->id]);

        return redirect()->route('problems.show', $problem)->with('success', 'Problemos apjungtos.');
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

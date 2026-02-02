<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Exports\ProblemsExport;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ProblemController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'category' => $request->get('category'),
            'report_number' => $request->get('report_number'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $problems = Problem::query()
            ->filtered($filters)
            ->sorted($sortBy, $sortDirection)
            ->paginate(6)
            ->withQueryString();

        $categories = Problem::distinct()->pluck('category')->sort()->values();

        return Inertia::render('Dashboard', [
            'problems' => $problems,
            'filters' => $filters,
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection,
            'categories' => $categories,
            'statusOptions' => ProblemStatus::selectableOptions(),
        ]);
    }

    public function updateStatus(Request $request, Problem $problem)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', ProblemStatus::values()),
        ]);

        $problem->update([
            'status' => $validated['status'],
            'status_updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Būsena atnaujinta');
    }

    public function bulkUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'problem_ids' => 'required|array',
            'problem_ids.*' => 'required|integer|exists:problems,id',
            'status' => 'required|string|in:' . implode(',', ProblemStatus::values()),
        ]);

        Problem::whereIn('id', $validated['problem_ids'])->update([
            'status' => $validated['status'],
            'status_updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Būsenos atnaujintos');
    }

    public function show(Problem $problem): Response
    {
        $categories = Problem::distinct()->pluck('category')->sort()->values();

        return Inertia::render('Problems/Show', [
            'problem' => $problem,
            'statusOptions' => ProblemStatus::selectableOptions(),
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Problem $problem)
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }

        $validated = $request->validate([
            'internal_number' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $problem->update($validated);

        return redirect()->back()->with('success', 'Problema atnaujinta');
    }

    public function destroy(Problem $problem)
    {
        $user = request()->user();
        if (! $user->hasRole(UserRole::ROLE_EMPLOYEE) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }

        $problem->delete();

        return redirect()->route('dashboard')->with('success', 'Problema pašalinta');
    }

    public function assign(Request $request): Response
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }

        $institutions = Institution::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($i) => ['id' => $i->id, 'name' => $i->name]);

        $problemIds = $request->get('problem_ids', []);

        $problem = null;
        $problems = [];

        if (!empty($problemIds)) {
            if (is_array($problemIds)) {
                $problems = Problem::whereIn('id', $problemIds)->get();
                if (count($problems) === 1) {
                    $problem = $problems->first();
                }
            } else {
                $problem = Problem::find($problemIds);
                if ($problem) {
                    $problems = [$problem];
                }
            }
        }

        return Inertia::render('Problems/Assign', [
            'problem' => $problem,
            'problems' => $problems,
            'institutions' => $institutions,
        ]);
    }

    public function assignSingle(Problem $problem): Response
    {
        $user = request()->user();
        if (! $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }

        $institutions = Institution::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($i) => ['id' => $i->id, 'name' => $i->name]);

        return Inertia::render('Problems/Assign', [
            'problem' => $problem,
            'problems' => [$problem],
            'institutions' => $institutions,
        ]);
    }

    public function reviewProgress(Problem $problem): Response
    {
        $problem->load('reviewHistory');

        $reviewEntries = $problem->reviewHistory->map(function ($entry) {
            return [
                'id' => $entry->id,
                'date' => $entry->created_at->toISOString(),
                'status' => $entry->status,
                'responsible' => $entry->responsible ?? '',
                'comment' => $entry->comment ?? '',
            ];
        })->toArray();

        return Inertia::render('Problems/ReviewProgress', [
            'problem' => $problem,
            'reviewEntries' => $reviewEntries,
        ]);
    }

    public function assignInstitution(Problem $problem): Response
    {
        $user = request()->user();
        if (! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }

        $employees = [];
        if ($user->institution_id) {
            $employees = Employee::where('institution_id', $user->institution_id)
                ->get(['id', 'name', 'surname', 'email'])
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => trim($e->name . ' ' . $e->surname) ?: $e->email,
                    'email' => $e->email,
                ]);
        }

        return Inertia::render('Problems/AssignInstitution', [
            'problem' => $problem,
            'employees' => $employees,
        ]);
    }

    public function merge(Request $request, Problem $problem): Response
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }

        $categoryFilter = $request->get('category');

        if ($categoryFilter === null || $categoryFilter === '') {
            $categoryFilter = '';
        }

        $categories = Problem::distinct()->pluck('category')->sort()->values();

        $query = Problem::where('id', '!=', $problem->id)
            ->where('status', '!=', 'neuzregistruota');

        if ($categoryFilter !== '') {
            $query->where('category', $categoryFilter);
        }

        $similarProblems = $query->orderBy('created_at', 'desc')->get();

        $mapCenter = [
            'lat' => 54.8985,
            'lng' => 23.9036,
        ];

        $mapMarkers = [];

        if ($problem->status !== 'neuzregistruota') {
            $mapMarkers[] = [
                'id' => $problem->id,
                'lat' => $mapCenter['lat'],
                'lng' => $mapCenter['lng'],
                'title' => $problem->title,
                'internal_number' => $problem->internal_number,
                'isMain' => true,
            ];
        }

        foreach ($similarProblems as $index => $p) {
            if ($p->status !== 'neuzregistruota') {
                $offset = (count($mapMarkers)) * 0.01;
                $mapMarkers[] = [
                    'id' => $p->id,
                    'lat' => $mapCenter['lat'] + ($offset * 0.001),
                    'lng' => $mapCenter['lng'] + ($offset * 0.001),
                    'title' => $p->title,
                    'internal_number' => $p->internal_number,
                ];
            }
        }

        return Inertia::render('Problems/Merge', [
            'problem' => $problem,
            'similarProblems' => $similarProblems,
            'categories' => $categories,
            'selectedCategory' => $categoryFilter,
            'mapCenter' => $mapCenter,
            'mapMarkers' => $mapMarkers,
        ]);
    }

    public function export(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }

        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'category' => $request->get('category'),
            'report_number' => $request->get('report_number'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $scope = $request->get('scope', 'current_page');

        $query = Problem::query()
            ->filtered($filters)
            ->sorted($sortBy, $sortDirection);

        if ($scope === 'current_page') {
            $page = $request->get('page', 1);
            $perPage = 6;
            $problems = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        } else {
            $problems = $query->get();
        }

        $filename = 'problemos_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(new ProblemsExport($problems), $filename);
    }

    public function map(): Response
    {
        $categories = Problem::distinct()->pluck('category')->sort()->values();

        return Inertia::render('Map', [
            'categories' => $categories,
            'statusOptions' => ProblemStatus::selectableOptions(),
        ]);
    }

    public function statistics(Request $request): Response
    {
        $period = $request->get('period', 'all');
        $institution = $request->get('institution', 'all');
        $category = $request->get('category', 'all');

        $categories = Problem::distinct()->pluck('category')->sort()->values();

        // TODO: Get institutions from database when model is available
        $institutions = [];

        $route = $request->route()->getName();

        if ($route === 'statistics.resolution-terms') {
            return Inertia::render('Statistics/ResolutionTerms');
        }

        if ($route === 'statistics.categories') {
            return Inertia::render('Statistics/Categories');
        }

        if ($route === 'statistics.institutions') {
            return Inertia::render('Statistics/Institutions');
        }

        return Inertia::render('Statistics', [
            'categories' => $categories,
            'institutions' => $institutions,
        ]);
    }
}

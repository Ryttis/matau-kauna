<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Exports\ProblemsExport;
use App\Models\Problem;
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
            'statusOptions' => ProblemStatus::options(),
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

    public function export(Request $request)
    {
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'category' => $request->get('category'),
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
}

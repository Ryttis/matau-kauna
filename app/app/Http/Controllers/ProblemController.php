<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Exports\ProblemsExport;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ProblemController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
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

        $employeeId = $this->employeeIdForUser($user);

        $problems = Problem::query()
            ->when(
                $user && $this->isInstitutionScoped($user),
                fn ($query) => $query->where('institution_id', $user->institution_id)
            )
            ->when(
                $user && $this->isEmployeeScoped($user),
                function ($query) use ($employeeId) {
                    if ($employeeId) {
                        $query->where('assigned_employee_id', $employeeId);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                }
            )
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
        $user = $request->user();
        if ($user && $this->isInstitutionScoped($user)) {
            if ((int) $problem->institution_id !== (int) $user->institution_id) {
                abort(403);
            }
        }
        if ($user && $this->isEmployeeScoped($user)) {
            $employeeId = $this->employeeIdForUser($user);
            if (! $employeeId || (int) $problem->assigned_employee_id !== (int) $employeeId) {
                abort(403);
            }
        }

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
        $user = $request->user();
        $validated = $request->validate([
            'problem_ids' => 'required|array',
            'problem_ids.*' => 'required|integer|exists:problems,id',
            'status' => 'required|string|in:' . implode(',', ProblemStatus::values()),
        ]);

        $query = Problem::whereIn('id', $validated['problem_ids']);
        if ($user && $this->isInstitutionScoped($user)) {
            $query->where('institution_id', $user->institution_id);
            $matchedCount = (clone $query)->count();
            if ($matchedCount !== count($validated['problem_ids'])) {
                abort(403);
            }
        }
        if ($user && $this->isEmployeeScoped($user)) {
            $employeeId = $this->employeeIdForUser($user);
            if (! $employeeId) {
                abort(403);
            }
            $query->where('assigned_employee_id', $employeeId);
            $matchedCount = (clone $query)->count();
            if ($matchedCount !== count($validated['problem_ids'])) {
                abort(403);
            }
        }

        $query->update([
            'status' => $validated['status'],
            'status_updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Būsenos atnaujintos');
    }

    public function show(Problem $problem): Response
    {
        $user = request()->user();
        if ($user && $this->isInstitutionScoped($user)) {
            if ((int) $problem->institution_id !== (int) $user->institution_id) {
                abort(403);
            }
        }
        if ($user && $this->isEmployeeScoped($user)) {
            $employeeId = $this->employeeIdForUser($user);
            if (! $employeeId || (int) $problem->assigned_employee_id !== (int) $employeeId) {
                abort(403);
            }
        }

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

    public function storeAssign(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }

        $validated = $request->validate([
            'problem_ids' => ['required', 'array'],
            'problem_ids.*' => ['required', 'integer', 'exists:problems,id'],
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
            'status' => ['nullable', 'string', 'in:' . implode(',', ProblemStatus::values())],
        ]);

        $update = [
            'institution_id' => $validated['institution_id'],
        ];

        if (! empty($validated['status'])) {
            $update['status'] = $validated['status'];
            $update['status_updated_at'] = now();
        }

        Problem::whereIn('id', $validated['problem_ids'])->update($update);

        $this->notifyInstitutionAdminsOfAssignment($validated['problem_ids'], $validated['institution_id']);

        return redirect()->route('dashboard')->with('success', 'Problemos priskirtos įstaigai.');
    }

    public function sendAssignEmail(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'problem_ids' => ['required', 'array'],
            'problem_ids.*' => ['required', 'integer', 'exists:problems,id'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
        ]);

        $problems = Problem::whereIn('id', $validated['problem_ids'])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'title', 'status', 'created_at']);

        $institutionName = null;
        if (! empty($validated['institution_id'])) {
            $institutionName = Institution::where('id', $validated['institution_id'])->value('name');
        }

        $lines = [];
        $lines[] = 'Priskirtų problemų sąrašas:';
        foreach ($problems as $problem) {
            $lines[] = sprintf(
                '- %s | %s | %s',
                $problem->internal_number,
                $problem->title,
                $problem->created_at?->format('Y-m-d')
            );
        }
        if ($institutionName) {
            $lines[] = '';
            $lines[] = 'Priskirta įstaiga: ' . $institutionName;
        }

        $subject = 'Problemų priskyrimo informacija';
        $body = implode("\n", $lines);

        Mail::raw($body, function ($message) use ($validated, $subject) {
            $message->to($validated['email'])->subject($subject);
        });

        return redirect()->back()->with('success', 'Informacija išsiųsta el. paštu.');
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
        $user = request()->user();
        if ($user && $this->isInstitutionScoped($user)) {
            if ((int) $problem->institution_id !== (int) $user->institution_id) {
                abort(403);
            }
        }
        if ($user && $this->isEmployeeScoped($user)) {
            $employeeId = $this->employeeIdForUser($user);
            if (! $employeeId || (int) $problem->assigned_employee_id !== (int) $employeeId) {
                abort(403);
            }
        }

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
        if ($this->isInstitutionScoped($user)) {
            if ((int) $problem->institution_id !== (int) $user->institution_id) {
                abort(403);
            }
        }

        $employees = [];
        if ($user->institution_id) {
            Employee::firstOrCreate(
                [
                    'email' => $user->email,
                    'institution_id' => $user->institution_id,
                ],
                [
                    'name' => $user->name ?? '',
                    'surname' => $user->surname ?? '',
                ]
            );

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

    public function storeAssignInstitution(Request $request, Problem $problem)
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }
        if ($this->isInstitutionScoped($user)) {
            if ((int) $problem->institution_id !== (int) $user->institution_id) {
                abort(403);
            }
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]);

        $employee = Employee::where('id', $validated['employee_id'])
            ->where('institution_id', $user->institution_id)
            ->first();

        if (! $employee) {
            abort(403);
        }

        $problem->update([
            'assigned_employee_id' => $employee->id,
        ]);

        $this->notifyEmployeeOfAssignment($problem, $employee);

        return redirect()->back()->with('success', 'Problema priskirta darbuotojui.');
    }

    public function merge(Request $request, Problem $problem): Response
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }
        if ($this->isInstitutionScoped($user)) {
            if ((int) $problem->institution_id !== (int) $user->institution_id) {
                abort(403);
            }
        }
        if ($this->isEmployeeScoped($user)) {
            $employeeId = $this->employeeIdForUser($user);
            if (! $employeeId || (int) $problem->assigned_employee_id !== (int) $employeeId) {
                abort(403);
            }
        }

        $categoryFilter = $request->get('category');

        if ($categoryFilter === null || $categoryFilter === '') {
            $categoryFilter = '';
        }

        $categories = Problem::distinct()->pluck('category')->sort()->values();

        $query = Problem::where('id', '!=', $problem->id)
            ->where('status', '!=', 'neuzregistruota');
        if ($this->isInstitutionScoped($user)) {
            $query->where('institution_id', $user->institution_id);
        }
        if ($this->isEmployeeScoped($user)) {
            $employeeId = $this->employeeIdForUser($user);
            if ($employeeId) {
                $query->where('assigned_employee_id', $employeeId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

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
            ->when(
                $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN),
                fn ($q) => $q->where('institution_id', $user->institution_id)
            )
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
        $user = request()->user();
        $categories = Problem::query()
            ->when(
                $user && $this->isInstitutionScoped($user),
                fn ($q) => $q->where('institution_id', $user->institution_id)
            )
            ->when(
                $user && $this->isEmployeeScoped($user),
                function ($q) use ($user) {
                    $employeeId = $this->employeeIdForUser($user);
                    if ($employeeId) {
                        $q->where('assigned_employee_id', $employeeId);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                }
            )
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return Inertia::render('Map', [
            'categories' => $categories,
            'statusOptions' => ProblemStatus::selectableOptions(),
        ]);
    }

    public function statistics(Request $request): Response
    {
        $user = $request->user();
        $period = $request->get('period', 'all');
        $institution = $request->get('institution', 'all');
        $category = $request->get('category', 'all');

        $categories = Problem::query()
            ->when(
                $user && $this->isInstitutionScoped($user),
                fn ($q) => $q->where('institution_id', $user->institution_id)
            )
            ->when(
                $user && $this->isEmployeeScoped($user),
                function ($q) use ($user) {
                    $employeeId = $this->employeeIdForUser($user);
                    if ($employeeId) {
                        $q->where('assigned_employee_id', $employeeId);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                }
            )
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

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

    private function isInstitutionScoped($user): bool
    {
        return $user
            && (
                $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)
                || $user->hasRole(UserRole::ROLE_EMPLOYEE)
                || $user->hasRole(UserRole::ROLE_MUNICIPALITY_REP)
            );
    }

    private function isEmployeeScoped($user): bool
    {
        return $user && $user->hasRole(UserRole::ROLE_EMPLOYEE);
    }

    private function employeeIdForUser($user): ?int
    {
        if (! $user) {
            return null;
        }

        return Employee::where('email', $user->email)
            ->where('institution_id', $user->institution_id)
            ->value('id');
    }

    private function notifyInstitutionAdminsOfAssignment(array $problemIds, int $institutionId): void
    {
        $problems = Problem::whereIn('id', $problemIds)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'title', 'created_at']);

        if ($problems->isEmpty()) {
            return;
        }

        $institution = Institution::find($institutionId);

        $admins = User::query()
            ->where('institution_id', $institutionId)
            ->where('email_updates_institution_admin', true)
            ->whereHas('roles', fn ($q) => $q->where('role', UserRole::ROLE_INSTITUTION_ADMIN))
            ->get(['email', 'name']);

        if ($admins->isEmpty()) {
            return;
        }

        $lines = [];
        $lines[] = 'Jums priskirtos naujos problemos:';
        foreach ($problems as $problem) {
            $lines[] = sprintf(
                '- %s | %s | %s | %s',
                $problem->internal_number,
                $problem->title,
                $problem->created_at?->format('Y-m-d'),
                route('problems.show', $problem->id)
            );
        }
        if ($institution) {
            $lines[] = '';
            $lines[] = 'Įstaiga: ' . $institution->name;
        }

        $subject = 'Naujos priskirtos problemos';
        $body = implode("\n", $lines);

        foreach ($admins as $admin) {
            Mail::raw($body, function ($message) use ($admin, $subject) {
                $message->to($admin->email)->subject($subject);
            });
        }
    }

    private function notifyEmployeeOfAssignment(Problem $problem, Employee $employee): void
    {
        $user = User::where('email', $employee->email)
            ->where('institution_id', $employee->institution_id)
            ->where('email_notifications_enabled', true)
            ->first(['email']);

        if (! $user) {
            return;
        }

        $subject = 'Jums priskirta nauja problema';
        $body = implode("\n", [
            'Jums priskirta nauja problema:',
            sprintf('%s | %s | %s', $problem->internal_number, $problem->title, route('problems.show', $problem->id)),
        ]);

        Mail::raw($body, function ($message) use ($user, $subject) {
            $message->to($user->email)->subject($subject);
        });
    }
}

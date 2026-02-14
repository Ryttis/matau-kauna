<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Exports\ProblemsExport;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\ProblemAutoAssignmentRule;
use App\Models\UserRole;
use App\Services\ProblemAssignmentNotifier;
use App\Services\ProblemAutoAssignmentService;
use App\Services\ProblemMergeContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ProblemController extends Controller
{
    public function __construct(
        private ProblemMergeContextService $mergeContextService,
        private ProblemAssignmentNotifier $assignmentNotifier,
        private ProblemAutoAssignmentService $autoAssignmentService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->hasRole(UserRole::ROLE_REPORTER) && ! $user->hasPermission('issues.view')) {
            $onlyMine = $request->boolean('only_mine');
            return Inertia::render('Dashboard', [
                'reporterView' => true,
                'onlyMine' => $onlyMine,
                'problems' => $this->getPublicProblemsForMap($onlyMine ? $user->id : null),
            ]);
        }

        if (! $user->hasPermission('issues.view')) {
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

        $employeeId = $this->employeeIdForUser($user);

        $problems = Problem::query()
            ->whereNull('merged_into_problem_id')
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
            ->with('mergedProblems')
            ->paginate(6)
            ->withQueryString();

        $categories = Problem::distinct()->pluck('category')->sort()->values();

        return Inertia::render('Dashboard', [
            'reporterView' => false,
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
        $this->authorizeProblemAccess($user, $problem);

        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', ProblemStatus::values()),
        ]);

        $ids = $problem->getMergeGroupIds();
        $query = Problem::whereIn('id', $ids);
        if ($user && $this->isInstitutionScoped($user)) {
            $query->where('institution_id', $user->institution_id);
        }
        if ($user && $this->isEmployeeScoped($user)) {
            $employeeId = $this->employeeIdForUser($user);
            if (! $employeeId) {
                abort(403);
            }
            $query->where('assigned_employee_id', $employeeId);
        }
        if ($query->count() !== count($ids)) {
            abort(403);
        }
        $query->update([
            'status' => $validated['status'],
            'status_updated_at' => now(),
        ]);

        $message = count($ids) > 1
            ? 'Būsena atnaujinta visoms apjungtoms problemoms.'
            : 'Būsena atnaujinta';

        return redirect()->back()->with('success', $message);
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
        $this->authorizeProblemAccess($user, $problem);

        $categories = Problem::distinct()->pluck('category')->sort()->values();

        $problem->load('mergedProblems:id,internal_number,merged_into_problem_id');
        $problem->load('mergedInto:id,internal_number,title');

        $mergeContext = $this->mergeContextService->forProblem($problem);

        return Inertia::render('Problems/Show', [
            'problem' => $problem,
            'mergeTarget' => $mergeContext['mergeTarget'],
            'mergedChildren' => $mergeContext['mergedChildren'],
            'mergedSiblings' => $mergeContext['mergedSiblings'],
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
            'category' => 'required|string|max:100',
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
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'status' => ['nullable', 'string', 'in:' . implode(',', ProblemStatus::values())],
        ]);

        $update = [
            'institution_id' => $validated['institution_id'] ?? null,
        ];

        if (empty($validated['institution_id'])) {
            $update['assigned_employee_id'] = null;
        }

        if (! empty($validated['status'])) {
            $update['status'] = $validated['status'];
            $update['status_updated_at'] = now();
        }

        Problem::whereIn('id', $validated['problem_ids'])->update($update);

        if (! empty($validated['institution_id'])) {
            $this->assignmentNotifier->notifyInstitutionAdminsOfAssignment($validated['problem_ids'], $validated['institution_id']);
            $this->assignmentNotifier->notifyInstitutionEmailOfAssignment($validated['problem_ids'], $validated['institution_id']);
        }

        $message = empty($validated['institution_id'])
            ? 'Problemos atšauktos nuo įstaigos.'
            : 'Problemos priskirtos įstaigai.';

        return redirect()->route('dashboard')->with('success', $message);
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
        if (! $user) {
            abort(403);
        }

        $isReporterOnly = $user->hasRole(UserRole::ROLE_REPORTER) && ! $user->hasPermission('issues.view');
        if ($isReporterOnly) {
            if ($problem->merged_into_problem_id) {
                $problem->load('mergedInto');
                $mainProblem = $problem->mergedInto ?? $problem;
            } else {
                $mainProblem = $problem;
            }
            $problem = $mainProblem;
            $problem->load('reviewHistory');
            return $this->reviewProgressResponse($problem, true);
        }

        $this->authorizeProblemAccess($user, $problem);
        if (! $user->hasPermission('issues.view')) {
            abort(403);
        }

        $problem->load('reviewHistory');

        return $this->reviewProgressResponse($problem, false);
    }

    private function reviewProgressResponse(Problem $problem, bool $reporterBackToDashboard): Response
    {
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
            'backUrl' => $reporterBackToDashboard ? route('dashboard') : route('problems.show', $problem->id),
        ]);
    }

    public function assignInstitution(Problem $problem): Response
    {
        $user = request()->user();
        if (! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }
        $this->authorizeProblemAccess($user, $problem);

        $employees = [];
        if ($user->institution_id) {
            $institutionUsers = User::where('institution_id', $user->institution_id)
                ->whereHas('roles', fn ($q) => $q->whereIn('role', [UserRole::ROLE_EMPLOYEE, UserRole::ROLE_INSTITUTION_ADMIN]))
                ->get(['id', 'name', 'surname', 'email']);

            foreach ($institutionUsers as $u) {
                Employee::firstOrCreate(
                    [
                        'email' => $u->email,
                        'institution_id' => $user->institution_id,
                    ],
                    [
                        'name' => $u->name ?? '',
                        'surname' => $u->surname ?? '',
                    ]
                );
            }

            $employees = Employee::where('institution_id', $user->institution_id)
                ->where('email', '!=', $user->email)
                ->orderBy('name')
                ->orderBy('surname')
                ->get(['id', 'name', 'surname', 'email'])
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => trim($e->name . ' ' . $e->surname) ?: $e->email,
                    'email' => $e->email,
                ])
                ->values()
                ->all();
        }

        $institutionsQuery = Institution::query()->orderBy('name');
        if ($this->isInstitutionScoped($user)) {
            $institutionsQuery->where('id', '!=', $user->institution_id);
        }
        $institutions = $institutionsQuery->get(['id', 'name'])->map(fn ($i) => ['id' => $i->id, 'name' => $i->name]);

        return Inertia::render('Problems/AssignInstitution', [
            'problem' => $problem,
            'employees' => $employees,
            'institutions' => $institutions,
        ]);
    }

    public function storeAssignInstitution(Request $request, Problem $problem)
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }
        $this->authorizeProblemAccess($user, $problem);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'completion_deadline' => ['nullable', 'date'],
        ]);

        $employee = Employee::where('id', $validated['employee_id'])
            ->where('institution_id', $user->institution_id)
            ->first();

        if (! $employee) {
            abort(403);
        }
        if ($employee->email === $user->email) {
            abort(403, 'Negalite priskirti problemos sau.');
        }

        $update = ['assigned_employee_id' => $employee->id];
        if (array_key_exists('completion_deadline', $validated)) {
            $update['completion_deadline'] = $validated['completion_deadline'] ?: null;
        }
        $problem->update($update);

        $this->assignmentNotifier->notifyEmployeeOfAssignment($problem, $employee);

        return redirect()->back()->with('success', 'Problema priskirta darbuotojui.');
    }

    public function sendAssignInstitutionEmail(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }
        $this->authorizeProblemAccess($user, $problem);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $lines = [
            'Problemos informacija:',
            sprintf('Nr: %s', $problem->internal_number ?? '-'),
            sprintf('Pavadinimas: %s', $problem->title ?? '-'),
            sprintf('Adresas: %s', $problem->address ?? '-'),
            sprintf('Sukūrimo data: %s', $problem->created_at?->format('Y-m-d') ?? '-'),
        ];
        if ($problem->completion_deadline) {
            $lines[] = sprintf('Atlikimo terminas: %s', $problem->completion_deadline->format('Y-m-d'));
        }
        $body = implode("\n", $lines);
        $subject = 'Problemos priskyrimo informacija – ' . ($problem->internal_number ?? 'problema');

        Mail::raw($body, function ($message) use ($validated, $subject) {
            $message->to($validated['email'])->subject($subject);
        });

        return redirect()->back()->with('success', 'Informacija išsiųsta el. paštu.');
    }

    public function returnToAdmin(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }
        if ($user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) || $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }
        if ($user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && $user->institution_id !== null) {
            if ((int) $problem->institution_id !== (int) $user->institution_id) {
                abort(403);
            }
        } elseif ($user->hasRole(UserRole::ROLE_EMPLOYEE)) {
            $employeeId = $this->employeeIdForUser($user);
            if (! $employeeId || (int) $problem->assigned_employee_id !== (int) $employeeId) {
                abort(403);
            }
        } else {
            abort(403);
        }

        $problem->update([
            'institution_id' => null,
            'assigned_employee_id' => null,
        ]);

        return redirect()->route('dashboard')->with('success', 'Problema grąžinta problemų administratoriui.');
    }

    public function reassignInstitution(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            if ($user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && $user->institution_id !== null) {
                if ((int) $problem->institution_id !== (int) $user->institution_id) {
                    abort(403);
                }
            } else {
                abort(403);
            }
        }

        $validated = $request->validate([
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
        ]);

        if ((int) $problem->institution_id === (int) $validated['institution_id']) {
            return redirect()->back()->with('info', 'Problema jau priskirta šiai įstaigai.');
        }

        $problem->update([
            'institution_id' => $validated['institution_id'],
            'assigned_employee_id' => null,
        ]);

        $this->assignmentNotifier->notifyInstitutionAdminsOfAssignment([$problem->id], $validated['institution_id']);
        $this->assignmentNotifier->notifyInstitutionEmailOfAssignment([$problem->id], $validated['institution_id']);

        return redirect()->back()->with('success', 'Problema perduota kitai įstaigai.');
    }

    public function merge(Request $request, Problem $problem): Response|RedirectResponse
    {
        $user = $request->user();
        $this->authorizeMergeAccess($user, $problem);
        if ($this->isEmployeeScoped($user)) {
            $employeeId = $this->employeeIdForUser($user);
            if (! $employeeId || (int) $problem->assigned_employee_id !== (int) $employeeId) {
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

        $categories = Problem::distinct()->pluck('category')->sort()->values();

        $query = Problem::where('id', '!=', $problem->id)
            ->where('status', '!=', 'neuzregistruota')
            ->whereNull('merged_into_problem_id');
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

        $similarProblems = $query->with('mergedProblems:id,internal_number,title,merged_into_problem_id,lat,lng')
            ->orderBy('created_at', 'desc')
            ->get();

        $problem->load(['mergedProblems.mergedProblems']);
        $directMerged = $problem->mergedProblems;
        $mergedProblems = $directMerged->flatMap(function ($m) {
            return collect([$m])->merge($m->mergedProblems ?? collect());
        })->values();

        $mapProblems = $this->buildMapProblemsForMerge($problem, $mergedProblems, $similarProblems);

        return Inertia::render('Problems/Merge', [
            'problem' => $problem,
            'mergedProblems' => $mergedProblems,
            'similarProblems' => $similarProblems,
            'categories' => $categories,
            'selectedCategory' => $categoryFilter,
            'mapProblems' => $mapProblems,
        ]);
    }

    public function storeMerge(Request $request, Problem $problem)
    {
        $user = $request->user();
        $this->authorizeMergeAccess($user, $problem);

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

        $query = Problem::whereIn('id', $idsToMerge);
        if ($this->isInstitutionScoped($user)) {
            $query->where('institution_id', $user->institution_id);
        }
        $query->update(['merged_into_problem_id' => $problem->id]);

        return redirect()->route('problems.show', $problem)->with('success', 'Problemos apjungtos.');
    }

    public function unmerge(Request $request, Problem $problem)
    {
        $this->authorizeMergeAccess($request->user(), $problem);
        if ($problem->merged_into_problem_id === null) {
            return redirect()->back()->with('info', 'Problema neapjungta.');
        }

        $problem->update(['merged_into_problem_id' => null]);

        return redirect()->back()->with('success', 'Problema atskirta.');
    }

    private function authorizeMergeAccess(User $user, Problem $problem): void
    {
        if (! $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }
        if ($this->isInstitutionScoped($user) && (int) $problem->institution_id !== (int) $user->institution_id) {
            abort(403);
        }
    }

    private function buildMapProblemsForMerge(Problem $problem, $mergedProblems, $similarProblems): array
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
            $hasCoords = $p->lat !== null && $p->lng !== null;
            $offset = $index * 0.002;
            $mapProblems[] = [
                'id' => $p->id,
                'internal_number' => $p->internal_number,
                'title' => $p->title,
                'status' => $p->status ?? 'nauja',
                'category' => $p->category,
                'address' => $p->address,
                'created_at' => $p->created_at?->toIso8601String(),
                'lat' => $hasCoords ? (float) $p->lat : $centerLat + ($offset * 0.1),
                'lng' => $hasCoords ? (float) $p->lng : $centerLng + ($offset * 0.1),
            ];
            $index++;
        }
        return $mapProblems;
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
            'problems' => $this->getProblemsForMapForUser($user),
        ]);
    }

    public function landing(): Response
    {
        return Inertia::render('Guest/Landing', [
            'problems' => $this->getPublicProblemsForMap(),
        ]);
    }

    public function publicMap(): Response
    {
        return Inertia::render('Guest/PublicMap', [
            'problems' => $this->getPublicProblemsForMap(),
        ]);
    }

    public function reporterReport(Request $request): Response
    {
        $user = $request->user();
        $fromProblems = Problem::query()->distinct()->pluck('category')->filter();
        $fromRules = ProblemAutoAssignmentRule::query()->distinct()->pluck('category')->filter();
        $categories = $fromProblems->merge($fromRules)->unique()->sort()->values();

        return Inertia::render('Reporter/Report', [
            'categories' => $categories,
            'showReporterWizard' => $user && ! $user->reporter_wizard_completed_at,
            'hasAcceptedReportTerms' => $user && $user->reporter_report_terms_accepted_at !== null,
        ]);
    }

    public function reporterWizardDismissed(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->user()?->update(['reporter_wizard_completed_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function reporterTermsAccepted(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->user()?->update(['reporter_report_terms_accepted_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function reporterReportStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(UserRole::ROLE_REPORTER)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'description' => 'required|string|max:65535',
            'address' => 'required|string|max:500',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $user->update(['reporter_report_terms_accepted_at' => now()]);

        $problem = Problem::create([
            'title' => Str::limit($validated['description'], 255),
            'category' => trim($validated['category']),
            'address' => $validated['address'],
            'lat' => $validated['lat'] ?? null,
            'lng' => $validated['lng'] ?? null,
            'description' => $validated['description'],
            'reported_by_user_id' => $user->id,
        ]);

        $problem->refresh();
        $this->autoAssignmentService->applyRules($problem);
        $problem->refresh();

        return response()->json(['ok' => true, 'problem_id' => $problem->id]);
    }

    public function publicShow(Request $request, Problem $problem): Response
    {
        $user = $request->user();
        $isReporterView = $user
            && $user->hasRole(UserRole::ROLE_REPORTER)
            && ! $user->hasPermission('issues.view');

        $problemData = [
            'id' => $problem->id,
            'internal_number' => $problem->internal_number,
            'title' => $problem->title,
            'status' => $problem->status,
            'category' => $problem->category,
            'address' => $problem->address,
            'description' => $problem->description,
            'created_at' => $problem->created_at?->toIso8601String(),
        ];

        $mergeContext = $this->mergeContextService->forProblem($problem);
        $reviewProgressProblemId = $problem->merged_into_problem_id && $problem->mergedInto
            ? $problem->mergedInto->id
            : $problem->id;

        if ($isReporterView) {
            return Inertia::render('Reporter/ProblemShow', [
                'problem' => $problemData,
                'mergeTarget' => $mergeContext['mergeTarget'],
                'mergedChildren' => $mergeContext['mergedChildren'],
                'mergedSiblings' => $mergeContext['mergedSiblings'],
                'reviewProgressProblemId' => $reviewProgressProblemId,
            ]);
        }

        return Inertia::render('Guest/PublicProblemShow', [
            'problem' => $problemData,
            'mergeTarget' => $mergeContext['mergeTarget'],
            'mergedChildren' => $mergeContext['mergedChildren'],
            'mergedSiblings' => $mergeContext['mergedSiblings'],
        ]);
    }

    private function getProblemsForMapForUser(?User $user): array
    {
        $center = config('kaunas.map_center');
        $centerLat = $center['lat'];
        $centerLng = $center['lng'];

        $query = Problem::query()
            ->whereIn('status', ['nauja', 'nagrinejama', 'isspresta'])
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
            );

        return $query
            ->orderBy('address')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'title', 'status', 'category', 'address', 'lat', 'lng', 'created_at'])
            ->map(function ($problem, $index) use ($centerLat, $centerLng) {
                $hasCoords = $problem->lat !== null && $problem->lng !== null;
                $offset = $index * 0.002;
                return [
                    'id' => $problem->id,
                    'internal_number' => $problem->internal_number,
                    'title' => $problem->title,
                    'status' => $problem->status,
                    'category' => $problem->category,
                    'address' => $problem->address,
                    'created_at' => $problem->created_at?->toIso8601String(),
                    'lat' => $hasCoords ? (float) $problem->lat : $centerLat + ($offset * 0.1),
                    'lng' => $hasCoords ? (float) $problem->lng : $centerLng + ($offset * 0.1),
                ];
            })
            ->values()
            ->all();
    }

    private function getPublicProblemsForMap(?int $reportedByUserId = null): array
    {
        $center = config('kaunas.map_center');
        $centerLat = $center['lat'];
        $centerLng = $center['lng'];
        $allowedStatuses = ['nauja', 'nagrinejama', 'isspresta'];

        $query = Problem::query()
            ->whereIn('status', $allowedStatuses)
            ->when($reportedByUserId !== null, function ($q) use ($reportedByUserId) {
                $q->where(function ($q2) use ($reportedByUserId) {
                    $q2->where('reported_by_user_id', $reportedByUserId)
                        ->orWhereHas('mergedInto', fn ($q3) => $q3->where('reported_by_user_id', $reportedByUserId));
                });
            })
            ->orderBy('address')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'title', 'status', 'category', 'address', 'lat', 'lng', 'created_at', 'merged_into_problem_id']);

        $index = 0;
        return $query->map(function ($problem) use ($centerLat, $centerLng, &$index) {
            $hasCoords = $problem->lat !== null && $problem->lng !== null;
            $offset = $index * 0.002;
            $index++;
            return [
                'id' => $problem->id,
                'internal_number' => $problem->internal_number,
                'title' => $problem->title,
                'status' => $problem->status,
                'category' => $problem->category,
                'address' => $problem->address,
                'created_at' => $problem->created_at?->toIso8601String(),
                'lat' => $hasCoords ? (float) $problem->lat : $centerLat + ($offset * 0.1),
                'lng' => $hasCoords ? (float) $problem->lng : $centerLng + ($offset * 0.1),
                'merged_into_problem_id' => $problem->merged_into_problem_id,
            ];
        })->values()->all();
    }

    public function statistics(Request $request): Response
    {
        $user = $request->user();

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

    private function authorizeProblemAccess($user, Problem $problem): void
    {
        if (! $user) {
            return;
        }
        if ($this->isInstitutionScoped($user)) {
            if ((int) $problem->institution_id !== (int) $user->institution_id) {
                abort(403);
            }
            return;
        }
        if ($this->isEmployeeScoped($user)) {
            $employeeId = $this->employeeIdForUser($user);
            if (! $employeeId || (int) $problem->assigned_employee_id !== (int) $employeeId) {
                abort(403);
            }
        }
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

        $query = Employee::where('email', $user->email);
        if ($user->institution_id !== null) {
            $query->where('institution_id', $user->institution_id);
            return $query->value('id');
        }
        $employees = $query->get(['id', 'institution_id']);
        if ($employees->count() === 1) {
            return $employees->first()->id;
        }
        return null;
    }

}

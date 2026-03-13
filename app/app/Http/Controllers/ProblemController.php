<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Exports\ProblemsExport;
use App\Exports\StatisticsReportExport;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\ClassifierValue;
use App\Models\Problem;
use App\Models\ProblemAutoAssignmentRule;
use App\Models\UserRole;
use App\Services\ProblemAssignmentNotifier;
use App\Services\ProblemAutoAssignmentService;
use App\Services\ProblemAutoPublishService;
use App\Services\ProblemMapService;
use App\Services\ProblemMergeContextService;
use App\Services\ProblemResolutionTermService;
use App\Services\ProblemScopeService;
use App\Services\StatisticsService;
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
        private ProblemMapService $mapService,
        private ProblemScopeService $scopeService,
        private ProblemAssignmentNotifier $assignmentNotifier,
        private ProblemAutoAssignmentService $autoAssignmentService,
        private ProblemAutoPublishService $autoPublishService,
        private ProblemResolutionTermService $resolutionTermService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->hasRole(UserRole::ROLE_REPORTER) && ! $user->hasPermission('issues.view')) {
            $onlyMine = $request->boolean('only_mine');
            return Inertia::render('Dashboard', [
                'reporterView' => true,
                'onlyMine' => $onlyMine,
                'problems' => $this->mapService->getPublicProblemsForMap($onlyMine ? $user->id : null),
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

        $employeeId = $this->scopeService->employeeIdForUser($user);

        $problems = Problem::query()
            ->whereNull('merged_into_problem_id')
            ->notArchived()
            ->when(
                $user && $this->scopeService->isInstitutionScoped($user),
                function ($query) use ($user) {
                    if ($user->institution_id === null) {
                        $query->whereRaw('1 = 0');
                    } else {
                        $query->where('institution_id', $user->institution_id);
                    }
                }
            )
            ->when(
                $user && $this->scopeService->isEmployeeScoped($user),
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

        $categories = ClassifierValue::getActiveTypeNames()->values();

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
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', ProblemStatus::values()),
        ]);

        $ids = $problem->getMergeGroupIds();
        $query = Problem::whereIn('id', $ids);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($ids));
        $query->update([
            'status' => $validated['status'],
            'status_updated_at' => now(),
        ]);

        $query->get()->each(fn (Problem $p) => $this->autoPublishService->applyRules($p));

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
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($validated['problem_ids']));
        $query->update([
            'status' => $validated['status'],
            'status_updated_at' => now(),
        ]);

        $query->get()->each(fn (Problem $p) => $this->autoPublishService->applyRules($p));

        return redirect()->back()->with('success', 'Būsenos atnaujintos');
    }

    public function updatePublish(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('issues.publish') && ! $user->hasPermission('issues.view')) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $validated = $request->validate([
            'is_published_publicly' => 'required|boolean',
        ]);

        $ids = $problem->getMergeGroupIds();
        $query = Problem::whereIn('id', $ids);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($ids));
        $query->update(['is_published_publicly' => $validated['is_published_publicly']]);

        $label = $validated['is_published_publicly'] ? 'Paskelbta viešai.' : 'Nuimta nuo viešo skelbimo.';
        return redirect()->back()->with('success', $label);
    }

    public function bulkUpdatePublish(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('issues.publish') && ! $user->hasPermission('issues.view')) {
            abort(403);
        }
        $validated = $request->validate([
            'problem_ids' => 'required|array',
            'problem_ids.*' => 'required|integer|exists:problems,id',
            'is_published_publicly' => 'required|boolean',
        ]);

        $query = Problem::whereIn('id', $validated['problem_ids']);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($validated['problem_ids']));
        $query->update(['is_published_publicly' => $validated['is_published_publicly']]);

        $label = $validated['is_published_publicly'] ? 'Problemos paskelbtos viešai.' : 'Problemos nuimtos nuo viešo skelbimo.';
        return redirect()->back()->with('success', $label);
    }

    public function show(Problem $problem): Response
    {
        $user = request()->user();
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $categories = ClassifierValue::getActiveTypeNames()->values();

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
        $canEdit = $user->canEditProblemClassification();
        if (! $canEdit) {
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
        $problem->refresh();
        $this->autoPublishService->applyRules($problem);

        return redirect()->back()->with('success', 'Problema atnaujinta');
    }

    public function destroy(Problem $problem)
    {
        $user = request()->user();
        if (! $user->hasRole(UserRole::ROLE_EMPLOYEE) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }

        $problem->update(['is_archived' => true, 'archived_at' => now()]);

        return redirect()->route('dashboard')->with('success', 'Problema perkelta į archyvą');
    }

    public function assign(Request $request): Response
    {
        $user = $request->user();
        if (! $user->isProblemAdminOrSystemAdmin()) {
            abort(403);
        }

        $institutions = Institution::query()
            ->notArchived()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($i) => ['id' => $i->id, 'name' => $i->name]);

        $problemIds = array_filter((array) $request->get('problem_ids', []));
        $problems = $problemIds ? Problem::whereIn('id', $problemIds)->get() : collect();
        $problem = $problems->count() === 1 ? $problems->first() : null;

        return Inertia::render('Problems/Assign', [
            'problem' => $problem,
            'problems' => $problems,
            'institutions' => $institutions,
        ]);
    }

    public function storeAssign(Request $request)
    {
        $user = $request->user();
        if (! $user->isProblemAdminOrSystemAdmin()) {
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
            foreach (Problem::whereIn('id', $validated['problem_ids'])->get() as $p) {
                $this->resolutionTermService->applyRules($p);
            }
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
        if (! $user->isProblemAdminOrSystemAdmin()) {
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
        if (! $user->isProblemAdminOrSystemAdmin()) {
            abort(403);
        }

        $institutions = Institution::query()
            ->notArchived()
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

        $this->scopeService->authorizeProblemAccess($user, $problem);
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

        $canAddEntry = ! $reporterBackToDashboard;

        return Inertia::render('Problems/ReviewProgress', [
            'problem' => $problem,
            'reviewEntries' => $reviewEntries,
            'backUrl' => $reporterBackToDashboard ? route('dashboard') : route('problems.show', $problem->id),
            'canAddEntry' => $canAddEntry,
        ]);
    }

    public function assignInstitution(Problem $problem): Response
    {
        $user = request()->user();
        if (! $user->isInstitutionAdminOrSystemAdmin()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

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

        $institutionsQuery = Institution::query()->notArchived()->orderBy('name');
        if ($this->scopeService->isInstitutionScoped($user)) {
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
        if (! $user->isInstitutionAdminOrSystemAdmin()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

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
        if (! $user->isInstitutionAdminOrSystemAdmin()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

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
            $employeeId = $this->scopeService->employeeIdForUser($user);
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
        if (! $user->isProblemAdminOrSystemAdmin()) {
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
        $this->scopeService->authorizeMergeAccess($user, $problem);
        if ($this->scopeService->isEmployeeScoped($user)) {
            $employeeId = $this->scopeService->employeeIdForUser($user);
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

        $categories = ClassifierValue::getActiveTypeNames()->values();

        $query = Problem::where('id', '!=', $problem->id)
            ->where('status', '!=', 'neuzregistruota')
            ->whereNull('merged_into_problem_id');
        if ($this->scopeService->isInstitutionScoped($user)) {
            $query->where('institution_id', $user->institution_id);
        }
        if ($this->scopeService->isEmployeeScoped($user)) {
            $employeeId = $this->scopeService->employeeIdForUser($user);
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

    public function storeMerge(Request $request, Problem $problem)
    {
        $user = $request->user();
        $this->scopeService->authorizeMergeAccess($user, $problem);

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
        if ($this->scopeService->isInstitutionScoped($user)) {
            $query->where('institution_id', $user->institution_id);
        }
        $query->update(['merged_into_problem_id' => $problem->id]);

        return redirect()->route('problems.show', $problem)->with('success', 'Problemos apjungtos.');
    }

    public function unmerge(Request $request, Problem $problem)
    {
        $this->scopeService->authorizeMergeAccess($request->user(), $problem);
        if ($problem->merged_into_problem_id === null) {
            return redirect()->back()->with('info', 'Problema neapjungta.');
        }

        $problem->update(['merged_into_problem_id' => null]);

        return redirect()->back()->with('success', 'Problema atskirta.');
    }

    public function export(Request $request)
    {
        $user = $request->user();
        if (! $user->isProblemAdminOrSystemAdmin()) {
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
            ->notArchived()
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
        $categories = ClassifierValue::getActiveTypeNames()->values();

        return Inertia::render('Map', [
            'categories' => $categories,
            'statusOptions' => ProblemStatus::selectableOptions(),
            'problems' => $this->mapService->getProblemsForMapForUser($user),
        ]);
    }

    public function landing(): Response
    {
        return Inertia::render('Guest/Landing', [
            'problems' => $this->mapService->getPublicProblemsForMap(),
        ]);
    }

    public function publicMap(): Response
    {
        return Inertia::render('Guest/PublicMap', [
            'problems' => $this->mapService->getPublicProblemsForMap(),
        ]);
    }

    public function reporterSarasas(Request $request): Response
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(UserRole::ROLE_REPORTER) || $user->hasPermission('issues.view')) {
            return redirect()->route('dashboard');
        }

        $filters = [
            'report_number' => $request->get('report_number'),
            'category' => $request->get('category'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        $problems = Problem::query()
            ->whereIn('status', ['nauja', 'nagrinejama', 'isspresta'])
            ->notArchived()
            ->whereNull('merged_into_problem_id')
            ->where(function ($q) use ($user) {
                $q->where('reported_by_user_id', $user->id)
                    ->orWhereHas('mergedProblems', fn ($q2) => $q2->where('reported_by_user_id', $user->id));
            })
            ->filtered($filters)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'status', 'created_at', 'title', 'category', 'address', 'description'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'internal_number' => $p->internal_number,
                'status' => $p->status,
                'created_at' => $p->created_at?->toIso8601String(),
                'title' => $p->title,
                'category' => $p->category,
                'address' => $p->address,
                'description' => $p->description ?? '',
            ])
            ->values()
            ->all();

        $categories = ClassifierValue::getActiveTypeNames()->values()->all();

        return Inertia::render('Reporter/Sarasas', [
            'problems' => $problems,
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    public function reporterReport(Request $request): Response
    {
        $user = $request->user();
        $types = ClassifierValue::notArchived()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with('activeChildren')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $typeValues = $types->map(fn (ClassifierValue $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'natures' => $t->activeChildren->map(fn (ClassifierValue $n) => ['id' => $n->id, 'name' => $n->name])->values()->all(),
        ])->values()->all();

        return Inertia::render('Reporter/Report', [
            'typeValues' => $typeValues,
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

        if (! $user?->hasRole(UserRole::ROLE_REPORTER)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'problem_type_id' => 'required|integer|exists:classifier_values,id',
            'problem_nature_id' => 'nullable|integer|exists:classifier_values,id',
            'description' => 'required|string|max:65535',
            'address' => 'required|string|max:500',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $typeId = (int) $validated['problem_type_id'];
        $natureId = isset($validated['problem_nature_id']) ? (int) $validated['problem_nature_id'] : null;
        $typeValue = ClassifierValue::where('id', $typeId)->whereNull('parent_id')->first();

        if (! $typeValue) {
            return response()->json(['message' => 'Invalid classifier value.'], 422);
        }

        if ($natureId !== null) {
            $natureValue = ClassifierValue::where('id', $natureId)->where('parent_id', $typeId)->first();

            if (! $natureValue) {
                return response()->json(['message' => 'Invalid classifier value.'], 422);
            }
        }

        $user->update(['reporter_report_terms_accepted_at' => now()]);
        $categoryName = $typeValue->name;

        $problem = Problem::create([
            'title' => Str::limit($validated['description'], 255),
            'category' => $categoryName,
            'problem_type_id' => $validated['problem_type_id'],
            'problem_nature_id' => $validated['problem_nature_id'] ?? null,
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
        $isReporterOnly = $user && $user->isReporterOnly();
        $isReporterOwnProblem = $isReporterOnly && (int) $problem->reported_by_user_id === (int) $user->id;
        $isPublic = $problem->is_published_publicly;

        if (! $isReporterOwnProblem && ! $isPublic) {
            abort(404);
        }

        $problemData = [
            'id' => $problem->id,
            'internal_number' => $problem->internal_number,
            'title' => $problem->title,
            'status' => $problem->status,
            'category' => $problem->category,
            'address' => $problem->address,
            'description' => $problem->description,
            'created_at' => $problem->created_at?->toIso8601String(),
            'is_published_publicly' => $problem->is_published_publicly,
        ];

        $mergeContext = $this->mergeContextService->forProblem($problem);
        $reviewProgressProblemId = $problem->merged_into_problem_id && $problem->mergedInto
            ? $problem->mergedInto->id
            : $problem->id;

        if ($isReporterOnly) {
            return Inertia::render('Reporter/ProblemShow', [
                'problem' => $problemData,
                'mergeTarget' => $mergeContext['mergeTarget'],
                'mergedChildren' => $mergeContext['mergedChildren'],
                'mergedSiblings' => $mergeContext['mergedSiblings'],
                'reviewProgressProblemId' => $reviewProgressProblemId,
                'backFrom' => $request->query('from'),
            ]);
        }

        return Inertia::render('Guest/PublicProblemShow', [
            'problem' => $problemData,
            'mergeTarget' => $mergeContext['mergeTarget'],
            'mergedChildren' => $mergeContext['mergedChildren'],
            'mergedSiblings' => $mergeContext['mergedSiblings'],
        ]);
    }

    public function statistics(Request $request): Response
    {
        $user = $request->user();

        $categories = ClassifierValue::getActiveTypeNames()->values();

        $institutions = [];

        $route = $request->route()->getName();

        if ($route === 'statistics.resolution-terms') {
            $query = Problem::query()
                ->whereNotNull('completion_deadline')
                ->whereNull('merged_into_problem_id')
                ->with(['institution:id,name', 'assignedEmployee:id,name,surname,email']);
            $this->scopeService->scopeProblemsForUser($query, $user);
            $all = $query->orderBy('completion_deadline')->get();
            $today = now()->startOfDay();
            $approachingEnd = $today->copy()->addDays(2);
            $approaching = $all->filter(fn ($p) => $p->completion_deadline && $p->completion_deadline->gte($today) && $p->completion_deadline->lte($approachingEnd) && $p->status !== 'isspresta');
            $overdue = $all->filter(fn ($p) => $p->completion_deadline && $p->completion_deadline->lt($today) && $p->status !== 'isspresta');
            return Inertia::render('Statistics/ResolutionTerms', [
                'approaching' => $approaching->values()->map(fn ($p) => [
                    'id' => $p->id,
                    'internal_number' => $p->internal_number,
                    'title' => $p->title,
                    'status' => $p->status,
                    'completion_deadline' => $p->completion_deadline?->format('Y-m-d'),
                    'institution_name' => $p->institution?->name,
                    'assigned_to' => $p->assignedEmployee ? trim($p->assignedEmployee->name . ' ' . $p->assignedEmployee->surname) : null,
                ])->all(),
                'overdue' => $overdue->values()->map(fn ($p) => [
                    'id' => $p->id,
                    'internal_number' => $p->internal_number,
                    'title' => $p->title,
                    'status' => $p->status,
                    'completion_deadline' => $p->completion_deadline?->format('Y-m-d'),
                    'institution_name' => $p->institution?->name,
                    'assigned_to' => $p->assignedEmployee ? trim($p->assignedEmployee->name . ' ' . $p->assignedEmployee->surname) : null,
                ])->all(),
            ]);
        }

        if ($route === 'statistics.categories') {
            return Inertia::render('Statistics/Categories');
        }

        if ($route === 'statistics.institutions') {
            return Inertia::render('Statistics/Institutions');
        }

        $service = app(StatisticsService::class);
        $period = $request->input('period', 'all');
        $institution = $request->input('institution', 'all');
        $category = $request->input('category', 'all');

        $institutions = $service->institutionsForUser($user)->map(fn ($i) => ['id' => $i->id, 'name' => $i->name])->values()->all();
        $categories = ClassifierValue::getActiveTypeNames()->values()->all();

        $query = $service->buildProblemQuery($user, $period, $institution, $category);
        $analytics = $service->getAnalytics($query);

        return Inertia::render('Statistics', [
            'categories' => $categories,
            'institutions' => $institutions,
            'filters' => [
                'period' => $period,
                'institution' => $institution,
                'category' => $category,
            ],
            'activeUsers' => $analytics['activeUsers'],
            'receivedReports' => $analytics['receivedReports'],
            'averageResolutionTime' => $analytics['averageResolutionTime'],
            'resolvedProblems' => $analytics['resolvedProblems'],
            'resolvedOnTime' => $analytics['resolvedOnTime'],
            'improvement' => $analytics['improvement'],
            'monthlyReports' => $analytics['monthlyReports'],
            'problematicCategories' => $analytics['problematicCategories'],
            'effectivelyResolved' => $analytics['effectivelyResolved'],
            'employeeActivity' => $analytics['employeeActivity'],
            'institutionResults' => $analytics['institutionResults'],
        ]);
    }

    public function statisticsExport(Request $request)
    {
        $user = $request->user();
        $service = app(StatisticsService::class);
        $period = $request->input('period', 'all');
        $institution = $request->input('institution', 'all');
        $category = $request->input('category', 'all');

        $query = $service->buildProblemQuery($user, $period, $institution, $category);
        $analytics = $service->getAnalytics($query);

        $institutionLabel = $institution && $institution !== 'all'
            ? (Institution::find($institution)?->name ?? $institution)
            : null;
        $categoryLabel = $category && $category !== 'all' ? $category : null;

        $filename = 'statistikos_ataskaita_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new StatisticsReportExport($analytics, $period, $institutionLabel, $categoryLabel),
            $filename
        );
    }
}

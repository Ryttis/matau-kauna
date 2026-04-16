<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\InstitutionTeam;
use App\Models\Problem;
use App\Models\User;
use App\Services\ProblemAssignmentNotifier;
use App\Services\ProblemScopeService;
use App\Services\TransactionalMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProblemInstitutionAssignmentController extends Controller
{
    public function __construct(
        private ProblemScopeService $scopeService,
        private ProblemAssignmentNotifier $assignmentNotifier,
    ) {}

    public function assignInstitution(Problem $problem): Response|RedirectResponse
    {
        $user = request()->user();
        if (! $user->canAssignInstitutionProblems()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->route('problems.show', $problem)->with('error', 'Išspręstos problemos priskirti negalima.');
        }

        $employees = [];
        $teams = [];
        if ($user->institution_id) {
            $institutionUsers = User::where('institution_id', $user->institution_id)
                ->whereHas('roles.permissions', fn ($query) => $query->where('permission', 'issues.assign'))
                ->get(['id', 'name', 'surname', 'email']);

            foreach ($institutionUsers as $institutionUser) {
                Employee::firstOrCreate(
                    [
                        'email' => $institutionUser->email,
                        'institution_id' => $user->institution_id,
                    ],
                    [
                        'name' => $institutionUser->name ?? '',
                        'surname' => $institutionUser->surname ?? '',
                    ]
                );
            }

            $employees = Employee::where('institution_id', $user->institution_id)
                ->where('email', '!=', $user->email)
                ->orderBy('name')
                ->orderBy('surname')
                ->get(['id', 'name', 'surname', 'email'])
                ->map(fn ($employee) => [
                    'id' => $employee->id,
                    'name' => trim($employee->name.' '.$employee->surname) ?: $employee->email,
                    'email' => $employee->email,
                ])
                ->values()
                ->all();

            $teams = InstitutionTeam::query()
                ->where('institution_id', $user->institution_id)
                ->withCount('employees')
                ->orderBy('name')
                ->get()
                ->map(fn (InstitutionTeam $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'members_count' => (int) $team->employees_count,
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
            'teams' => $teams,
            'institutions' => $institutions,
        ]);
    }

    public function storeAssignInstitution(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canAssignInstitutionProblems()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->back()->with('error', 'Išspręstos problemos priskirti negalima.');
        }

        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'institution_team_id' => ['nullable', 'integer', 'exists:institution_teams,id'],
            'completion_deadline' => ['nullable', 'date'],
        ]);

        $employeeId = $validated['employee_id'] ?? null;
        $teamId = $validated['institution_team_id'] ?? null;

        if (($employeeId === null) === ($teamId === null)) {
            return redirect()->back()->with('error', 'Pasirinkite darbuotoją arba komandą.');
        }

        $deadline = array_key_exists('completion_deadline', $validated)
            ? ($validated['completion_deadline'] ?: null)
            : null;

        if ($employeeId !== null) {
            $employee = Employee::where('id', $employeeId)
                ->where('institution_id', $user->institution_id)
                ->first();

            if (! $employee) {
                abort(403);
            }
            if ($employee->email === $user->email) {
                abort(403, 'Negalite priskirti problemos sau.');
            }

            $problem->update([
                'assigned_employee_id' => $employee->id,
                'assigned_institution_team_id' => null,
                'completion_deadline' => $deadline,
            ]);

            $this->assignmentNotifier->notifyEmployeeOfAssignment($problem, $employee);

            return redirect()->back()->with('success', 'Problema priskirta darbuotojui.');
        }

        $team = InstitutionTeam::query()
            ->where('id', $teamId)
            ->where('institution_id', $user->institution_id)
            ->with('employees')
            ->first();

        if (! $team) {
            abort(403);
        }
        if ($team->employees->isEmpty()) {
            return redirect()->back()->with('error', 'Komanda neturi narių.');
        }

        $problem->update([
            'assigned_employee_id' => null,
            'assigned_institution_team_id' => $team->id,
            'completion_deadline' => $deadline,
        ]);

        foreach ($team->employees as $employee) {
            if ($employee->email !== $user->email) {
                $this->assignmentNotifier->notifyEmployeeOfAssignment($problem->fresh(), $employee);
            }
        }

        return redirect()->back()->with('success', 'Problema priskirta komandai.');
    }

    public function sendAssignInstitutionEmail(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canAssignInstitutionProblems()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->back()->with('error', 'Išspręstos problemos priskirti negalima.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $mail = $this->assignmentNotifier->buildInstitutionProblemShareEmail($problem);
        TransactionalMail::send(
            $validated['email'],
            $mail['subject'],
            $mail['body'],
            $mail['action_url'] ?? null,
            $mail['action_label'] ?? null,
        );

        return redirect()->back()->with('success', 'Informacija išsiųsta el. paštu.');
    }

    public function returnToAdmin(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }
        if ($user->canReassignInstitutions()) {
            abort(403);
        }
        if ($this->scopeService->isInstitutionScoped($user) && $user->institution_id !== null) {
            if ((int) $problem->institution_id !== (int) $user->institution_id) {
                abort(403);
            }
        } elseif ($this->scopeService->isEmployeeScoped($user)) {
            $employeeId = $this->scopeService->employeeIdForUser($user);
            if (! $this->scopeService->employeeHasAccessToAssignment($employeeId, $problem)) {
                abort(403);
            }
        } else {
            abort(403);
        }

        $problem->update([
            'institution_id' => null,
            'assigned_employee_id' => null,
            'assigned_institution_team_id' => null,
        ]);

        return redirect()->route('dashboard')->with('success', 'Problema grąžinta problemų administratoriui.');
    }

    public function reassignInstitution(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        $canReassign = $user->canReassignInstitutions()
            || ($this->scopeService->isInstitutionScoped($user)
                && $user->institution_id !== null
                && (int) $problem->institution_id === (int) $user->institution_id);

        if (! $canReassign) {
            abort(403);
        }

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->back()->with('error', 'Išspręstos problemos perduoti negalima.');
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
            'assigned_institution_team_id' => null,
        ]);

        $this->assignmentNotifier->notifyInstitutionOfAssignment([$problem->id], $validated['institution_id']);

        return redirect()->back()->with('success', 'Problema perduota kitai įstaigai.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ProblemAssignmentNotifier;
use App\Services\ProblemScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
        if (! $user->isInstitutionAdminOrSystemAdmin()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->route('problems.show', $problem)->with('error', 'Išspręstos problemos priskirti negalima.');
        }

        $employees = [];
        if ($user->institution_id) {
            $institutionUsers = User::where('institution_id', $user->institution_id)
                ->whereHas('roles', fn ($query) => $query->whereIn('role', [UserRole::ROLE_EMPLOYEE, UserRole::ROLE_INSTITUTION_ADMIN]))
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

    public function storeAssignInstitution(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isInstitutionAdminOrSystemAdmin()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->back()->with('error', 'Išspręstos problemos priskirti negalima.');
        }

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

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->back()->with('error', 'Išspręstos problemos priskirti negalima.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $lines = [
            'Problemos informacija:',
            sprintf('Nr: %s', $problem->internal_number ?? '-'),
            sprintf('Aprašymas: %s', Problem::descriptionPreview($problem->description, 500) ?: '-'),
            sprintf('Adresas: %s', $problem->address ?? '-'),
            sprintf('Sukūrimo data: %s', $problem->created_at?->format('Y-m-d') ?? '-'),
        ];
        if ($problem->completion_deadline) {
            $lines[] = sprintf('Atlikimo terminas: %s', $problem->completion_deadline->format('Y-m-d'));
        }
        $body = implode("\n", $lines);
        $subject = 'Problemos priskyrimo informacija – '.($problem->internal_number ?? 'problema');

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
        $canReassign = $user->isProblemAdminOrSystemAdmin()
            || ($user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)
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
        ]);

        $this->assignmentNotifier->notifyInstitutionOfAssignment([$problem->id], $validated['institution_id']);

        return redirect()->back()->with('success', 'Problema perduota kitai įstaigai.');
    }
}

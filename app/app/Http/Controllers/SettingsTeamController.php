<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\InstitutionTeam;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SettingsTeamController extends Controller
{
    public function storeTeam(Request $request): mixed
    {
        $user = Auth::user();
        if (! ($user instanceof User)) {
            abort(403);
        }
        $this->ensureTeamManagementAccess($user);

        $isSystemAdmin = $user->canAccessAdmin();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'institution_id' => [
                'nullable',
                'integer',
                'exists:institutions,id',
            ],
        ]);

        $institutionId = $isSystemAdmin
            ? ($validated['institution_id'] ?? $user->institution_id)
            : $user->institution_id;

        if (! $institutionId) {
            abort(403);
        }

        $team = InstitutionTeam::create([
            'institution_id' => $institutionId,
            'name' => $validated['name'],
        ]);

        return $team->load(
            $this->assignableEmployeesEagerLoadForInstitution($institutionId, 'id,name,surname,email')
        );
    }

    public function updateTeam(Request $request, InstitutionTeam $team): mixed
    {
        $user = Auth::user();
        if (! ($user instanceof User)) {
            abort(403);
        }
        $this->ensureTeamManagementAccess($user);
        $this->ensureCanManageTeam($user, $team);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $team->update($validated);

        return $team->fresh()->load(
            $this->assignableEmployeesEagerLoadForInstitution($team->institution_id, 'id,name,surname,email')
        );
    }

    public function destroyTeam(InstitutionTeam $team): mixed
    {
        $user = Auth::user();
        if (! ($user instanceof User)) {
            abort(403);
        }
        $this->ensureTeamManagementAccess($user);
        $this->ensureCanManageTeam($user, $team);

        $team->delete();

        return response()->noContent();
    }

    public function syncTeamMembers(Request $request, InstitutionTeam $team): mixed
    {
        $user = Auth::user();
        if (! ($user instanceof User)) {
            abort(403);
        }
        $this->ensureTeamManagementAccess($user);
        $this->ensureCanManageTeam($user, $team);

        $assignableIds = $this->assignableEmployeeIdsForInstitution($team->institution_id);
        $validated = $request->validate([
            'employee_ids' => ['array'],
            'employee_ids.*' => [
                'integer',
                Rule::in($assignableIds->all()),
            ],
        ]);

        $team->employees()->sync($validated['employee_ids'] ?? []);

        return $team->fresh()->load(
            $this->assignableEmployeesEagerLoadForInstitution($team->institution_id, 'id,name,surname,email')
        );
    }

    public function listTeams(Request $request): mixed
    {
        $user = Auth::user();
        if (! ($user instanceof User)) {
            abort(403);
        }
        $this->ensureTeamManagementAccess($user);

        $isSystemAdmin = $user->canAccessAdmin();
        $institutionId = $isSystemAdmin
            ? $request->integer('institution_id', $user->institution_id)
            : $user->institution_id;

        if (! $institutionId) {
            abort(403);
        }

        if ($isSystemAdmin) {
            $request->validate([
                'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            ]);
        }

        $assignableEmails = User::queryAssignableInstitutionEmployeeUsers($institutionId)->pluck('email');
        if ($assignableEmails->isEmpty()) {
            $employees = collect();
        } else {
            $employees = Employee::query()
                ->where('institution_id', $institutionId)
                ->whereIn('email', $assignableEmails)
                ->select(['id', 'name', 'surname', 'email'])
                ->orderBy('surname')
                ->get();
        }

        $teams = InstitutionTeam::query()
            ->where('institution_id', $institutionId)
            ->with($this->assignableEmployeesEagerLoadForInstitution($institutionId, 'id,name,surname,email'))
            ->withCount([
                'employees as members_count' => function ($q) use ($assignableEmails) {
                    if ($assignableEmails->isEmpty()) {
                        $q->whereRaw('0 = 1');
                    } else {
                        $q->whereIn('email', $assignableEmails);
                    }
                },
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'employees' => $employees,
            'teams' => $teams,
        ]);
    }

    private function assignableInstitutionEmployeeEmails(int $institutionId): \Illuminate\Support\Collection
    {
        return User::queryAssignableInstitutionEmployeeUsers($institutionId)->pluck('email');
    }

    private function assignableEmployeeIdsForInstitution(int $institutionId): \Illuminate\Support\Collection
    {
        $emails = $this->assignableInstitutionEmployeeEmails($institutionId);
        if ($emails->isEmpty()) {
            return collect();
        }

        return Employee::query()
            ->where('institution_id', $institutionId)
            ->whereIn('email', $emails)
            ->pluck('id');
    }

    private function assignableEmployeesEagerLoadForInstitution(int $institutionId, string $selectColumns): array
    {
        $emails = $this->assignableInstitutionEmployeeEmails($institutionId);
        $selectFields = array_map('trim', explode(',', $selectColumns));
        $closure = function ($q) use ($emails, $selectFields) {
            if ($emails->isEmpty()) {
                $q->whereRaw('0 = 1');
            } else {
                $q->select($selectFields);
                $q->whereIn('email', $emails);
            }
        };

        return [
            'employees' => $closure,
        ];
    }

    private function ensureSameInstitution(User $user, InstitutionTeam $team): void
    {
        if ((int) $team->institution_id !== (int) $user->institution_id) {
            abort(403);
        }
    }

    private function ensureTeamManagementAccess(?User $user): void
    {
        if (! $user) {
            abort(403);
        }

        if (! $user->canManageTeams()) {
            abort(403);
        }
    }

    private function ensureCanManageTeam(User $user, InstitutionTeam $team): void
    {
        if ($user->canAccessAdmin()) {
            return;
        }

        $this->ensureSameInstitution($user, $team);
    }
}

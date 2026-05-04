<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProblemScopeService
{
    public function scopeProblemsForUser(Builder $query, ?User $user): void
    {
        if (! $user) {
            return;
        }

        if ($user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)) {
            if ($user->institution_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $this->scopeInstitutionProblems($query, (int) $user->institution_id);
            }

            return;
        }

        if ($this->restrictsToOwnAssignmentsOnly($user)) {
            $employeeId = $this->employeeIdForUser($user);

            if (! $employeeId) {
                abort(403);
            }

            $query->whereVisibleToEmployee($employeeId);

            return;
        }
    }

    public function ensureScopeMatchesCount(Builder $query, int $expectedCount): void
    {
        $actualCount = (clone $query)->count();

        if ($actualCount !== $expectedCount) {
            abort(403);
        }
    }

    public function authorizeProblemAccess(?User $user, Problem $problem): void
    {
        if (! $user) {
            return;
        }

        if ($user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)) {
            $this->authorizeInstitutionProblemAccess($user, $problem);

            return;
        }

        if ($this->restrictsToOwnAssignmentsOnly($user)) {
            $employeeId = $this->employeeIdForUser($user);

            if (! $this->employeeHasAccessToAssignment($employeeId, $problem)) {
                abort(403);
            }

            return;
        }
    }

    public function employeeHasAccessToAssignment(?int $employeeId, Problem $problem): bool
    {
        if (! $employeeId) {
            return false;
        }
        if ((int) $problem->assigned_employee_id === (int) $employeeId) {
            return true;
        }
        if ($problem->assigned_institution_team_id === null) {
            return false;
        }

        return DB::table('employee_institution_team')
            ->where('institution_team_id', $problem->assigned_institution_team_id)
            ->where('employee_id', $employeeId)
            ->exists();
    }

    public function scopeInstitutionProblems(Builder $query, int $institutionId): void
    {
        $table = $query->getModel()->getTable();
        $query->where(function (Builder $inner) use ($institutionId, $table) {
            $inner->where("{$table}.institution_id", $institutionId)
                ->orWhereExists(function ($sub) use ($institutionId, $table) {
                    $sub->selectRaw('1')
                        ->from('problems as merge_parents')
                        ->whereColumn('merge_parents.id', "{$table}.merged_into_problem_id")
                        ->where('merge_parents.institution_id', $institutionId);
                });
        });
    }

    private function authorizeInstitutionProblemAccess(User $user, Problem $problem): void
    {
        if ($user->institution_id === null) {
            abort(403);
        }

        $iid = (int) $user->institution_id;
        if ((int) $problem->institution_id === $iid) {
            return;
        }

        if ($problem->merged_into_problem_id === null) {
            abort(403);
        }

        $allowed = Problem::query()
            ->where('id', (int) $problem->merged_into_problem_id)
            ->where('institution_id', $iid)
            ->exists();

        if (! $allowed) {
            abort(403);
        }
    }

    public function authorizeMergeAccess(User $user, Problem $problem): void
    {
        if (! $user->canMergeProblems()) {
            abort(403);
        }

        if (! $this->isInstitutionScoped($user)) {
            return;
        }

        $this->authorizeInstitutionProblemAccess($user, $problem);
    }

    public function isInstitutionScoped(?User $user): bool
    {
        return $user
            && (
                $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)
                || $user->hasRole(UserRole::ROLE_EMPLOYEE)
            );
    }

    public function isEmployeeScoped(?User $user): bool
    {
        return $user && $user->hasRole(UserRole::ROLE_EMPLOYEE);
    }

    public function restrictsToOwnAssignmentsOnly(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            return false;
        }
        if ($user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)) {
            return false;
        }

        return $user->hasRole(UserRole::ROLE_EMPLOYEE)
            || $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN);
    }

    public function employeeIdForUser(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        $query = Employee::where('email', $user->email);

        if ($user->institution_id !== null) {
            return $query->where('institution_id', $user->institution_id)->value('id');
        }

        $employees = $query->get(['id', 'institution_id']);

        return $employees->count() === 1 ? $employees->first()->id : null;
    }
}

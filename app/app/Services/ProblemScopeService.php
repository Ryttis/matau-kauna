<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Builder;

class ProblemScopeService
{
    public function scopeProblemsForUser(Builder $query, ?User $user): void
    {
        if (! $user) {
            return;
        }

        if ($this->isInstitutionScoped($user)) {
            if ($user->institution_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('institution_id', $user->institution_id);
            }

            return;
        }

        if (! $this->isEmployeeScoped($user)) {
            return;
        }

        $employeeId = $this->employeeIdForUser($user);

        if (! $employeeId) {
            abort(403);
        }

        $query->where('assigned_employee_id', $employeeId);
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

        if ($this->isInstitutionScoped($user)) {
            $this->authorizeInstitutionMatch($user, (int) $problem->institution_id);

            return;
        }

        if (! $this->isEmployeeScoped($user)) {
            return;
        }

        $employeeId = $this->employeeIdForUser($user);
        $problemEmployeeId = (int) $problem->assigned_employee_id;

        if (! $employeeId || $problemEmployeeId !== (int) $employeeId) {
            abort(403);
        }
    }

    private function authorizeInstitutionMatch(User $user, int $institutionId): void
    {
        $userInstitutionId = (int) $user->institution_id;

        if ($institutionId !== $userInstitutionId) {
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

        $this->authorizeInstitutionMatch($user, (int) $problem->institution_id);
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

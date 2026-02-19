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
    }

    public function ensureScopeMatchesCount(Builder $query, int $expectedCount): void
    {
        if ((clone $query)->count() !== $expectedCount) {
            abort(403);
        }
    }

    public function authorizeProblemAccess(?User $user, Problem $problem): void
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

    public function authorizeMergeAccess(User $user, Problem $problem): void
    {
        if (! $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }
        if ($this->isInstitutionScoped($user) && (int) $problem->institution_id !== (int) $user->institution_id) {
            abort(403);
        }
    }

    public function isInstitutionScoped(?User $user): bool
    {
        return $user
            && (
                $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)
                || $user->hasRole(UserRole::ROLE_EMPLOYEE)
                || $user->hasRole(UserRole::ROLE_MUNICIPALITY_REP)
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

<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Hash;

class RbacService
{
    public function validateSyncUserRoles(User $user, array $roleIds): ?array
    {
        $currentRoleIds = $user->roles()->pluck('id')->toArray();
        $addedRoleIds = array_values(array_diff($roleIds, $currentRoleIds));

        if (empty($addedRoleIds)) {
            return null;
        }

        $restrictedRoleIds = UserRole::query()
            ->whereIn('role', [UserRole::ROLE_INSTITUTION_ADMIN, UserRole::ROLE_EMPLOYEE])
            ->pluck('id')
            ->toArray();

        $addedRestricted = array_values(array_intersect($addedRoleIds, $restrictedRoleIds));
        if (empty($addedRestricted)) {
            return null;
        }

        $missingInvite = collect($addedRestricted)->first(function ($roleId) use ($user) {
            return ! Invitation::query()
                ->where('email', $user->email)
                ->where('institution_id', $user->institution_id)
                ->where('role_id', $roleId)
                ->whereNotNull('used_at')
                ->exists();
        });

        if ($missingInvite) {
            return [
                'message' => 'Įstaigos administratorių ar darbuotojų paskyros turi būti kuriamos per pakvietimą.',
            ];
        }

        return null;
    }

    public function ensureEmployeeForInstitution(User $user): void
    {
        if (! $user->institution_id) {
            Employee::where('email', $user->email)->delete();

            return;
        }

        $employee = Employee::firstOrCreate(
            [
                'email' => $user->email,
                'institution_id' => $user->institution_id,
            ],
            [
                'name' => $user->name ?? '',
                'surname' => '',
            ]
        );

        if ($employee->wasRecentlyCreated) {
            return;
        }

        $employee->name = $user->name ?? $employee->name;
        $employee->save();
    }

    public function createUser(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'institution_id' => $data['institution_id'] ?? null,
        ]);

        $this->ensureEmployeeForInstitution($user);

        return $user;
    }

    public function updateUser(User $user, array $data): void
    {
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->institution_id = array_key_exists('institution_id', $data) ? $data['institution_id'] : $user->institution_id;

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $this->ensureEmployeeForInstitution($user);
    }
}

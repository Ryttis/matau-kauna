<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserRolePermission;
use App\Models\UserRolePermissionGroup;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Employee;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class RbacController extends Controller
{
    public function users()
    {
        return User::query()
            ->notArchived()
            ->select(['id', 'name', 'email', 'institution_id'])
            ->with(['roles:id,role,name', 'institution:id,name'])
            ->orderBy('id', 'desc')
            ->paginate(20);
    }

    public function syncUserRoles(Request $request, User $user)
    {
        $data = $request->validate([
            'role_ids' => ['array'],
            'role_ids.*' => ['uuid', 'exists:user_role,id'],
        ]);

        $roleIds = $data['role_ids'] ?? [];
        $currentRoleIds = $user->roles()->pluck('id')->toArray();
        $addedRoleIds = array_values(array_diff($roleIds, $currentRoleIds));

        if (! empty($addedRoleIds)) {
            $restrictedRoleIds = UserRole::query()
                ->whereIn('role', [UserRole::ROLE_INSTITUTION_ADMIN, UserRole::ROLE_EMPLOYEE])
                ->pluck('id')
                ->toArray();

            $addedRestricted = array_values(array_intersect($addedRoleIds, $restrictedRoleIds));
            if (! empty($addedRestricted)) {
                $missingInvite = collect($addedRestricted)->first(function ($roleId) use ($user) {
                    return ! Invitation::query()
                        ->where('email', $user->email)
                        ->where('institution_id', $user->institution_id)
                        ->where('role_id', $roleId)
                        ->whereNotNull('used_at')
                        ->exists();
                });

                if ($missingInvite) {
                    return response()->json([
                        'message' => 'Įstaigos administratorių ar darbuotojų paskyros turi būti kuriamos per pakvietimą.',
                    ], 422);
                }
            }
        }

        $user->roles()->sync($data['role_ids'] ?? []);

        return $user->load('roles:id,role,name');
    }

    public function roles()
    {
        return UserRole::query()
            ->with(['permissionGroups:id,name'])
            ->orderBy('name')
            ->get();
    }

    public function createRole(Request $request)
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'max:255', 'unique:user_role,role'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        return UserRole::create($data);
    }

    public function groups()
    {
        return UserRolePermissionGroup::query()
            ->with(['permissions:id,permission', 'roles:id,role,name'])
            ->orderBy('name')
            ->get();
    }

    public function createGroup(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        return UserRolePermissionGroup::create($data);
    }

    public function permissions()
    {
        return UserRolePermission::query()
            ->orderBy('permission')
            ->get();
    }

    public function createPermission(Request $request)
    {
        $data = $request->validate([
            'permission' => ['required', 'string', 'max:255', 'unique:user_role_permission,permission'],
        ]);

        return UserRolePermission::create($data);
    }

    public function syncRoleGroups(Request $request, UserRole $role)
    {
        $data = $request->validate([
            'group_ids' => ['array'],
            'group_ids.*' => ['uuid', 'exists:user_role_permission_group,id'],
        ]);

        $role->permissionGroups()->sync($data['group_ids'] ?? []);

        return $role->load('permissionGroups:id,name');
    }

    public function syncGroupPermissions(Request $request, UserRolePermissionGroup $group)
    {
        $data = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['uuid', 'exists:user_role_permission,id'],
        ]);

        $group->permissions()->sync($data['permission_ids'] ?? []);

        return $group->load('permissions:id,permission');
    }

    public function createUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'institution_id' => $data['institution_id'] ?? null,
        ]);

        $this->ensureEmployeeForInstitution($user);

        return $user->load(['roles:id,role,name', 'institution:id,name']);
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->institution_id = array_key_exists('institution_id', $data) ? $data['institution_id'] : $user->institution_id;

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        $this->ensureEmployeeForInstitution($user);

        return $user->load(['roles:id,role,name', 'institution:id,name']);
    }

    public function deleteUser(User $user)
    {
        if (auth()->id() === $user->id) {
            abort(422, 'Negalima ištrinti savo naudotojo.');
        }

        $user->update(['archived_at' => now()]);

        return response()->noContent();
    }

    public function deleteRole(UserRole $role)
    {
        $role->users()->detach();
        $role->permissionGroups()->detach();
        $role->delete();

        return response()->noContent();
    }

    public function deleteGroup(UserRolePermissionGroup $group)
    {
        $group->roles()->detach();
        $group->permissions()->detach();
        $group->delete();

        return response()->noContent();
    }

    public function deletePermission(UserRolePermission $permission)
    {
        $permission->groups()->detach();
        $permission->delete();

        return response()->noContent();
    }

    private function ensureEmployeeForInstitution(User $user): void
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
}

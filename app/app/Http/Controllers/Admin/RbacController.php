<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RolePermission;
use App\Models\RolePermissionGroup;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class RbacController extends Controller
{
    public function users()
    {
        return User::query()
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
        return RolePermissionGroup::query()
            ->with(['permissions:id,permission', 'roles:id,role,name'])
            ->orderBy('name')
            ->get();
    }

    public function createGroup(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        return RolePermissionGroup::create($data);
    }

    public function permissions()
    {
        return RolePermission::query()
            ->orderBy('permission')
            ->get();
    }

    public function createPermission(Request $request)
    {
        $data = $request->validate([
            'permission' => ['required', 'string', 'max:255', 'unique:user_role_permission,permission'],
        ]);

        return RolePermission::create($data);
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

    public function syncGroupPermissions(Request $request, RolePermissionGroup $group)
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

        return $user->load(['roles:id,role,name', 'institution:id,name']);
    }

    public function deleteUser(User $user)
    {
        if (auth()->id() === $user->id) {
            abort(422, 'Negalima ištrinti savo naudotojo.');
        }

        $user->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\SyncUserRolesRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use App\Models\UserRolePermissionGroup;
use App\Services\AuditLogService;
use App\Services\RbacService;
use Illuminate\Http\Request;

class RbacController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
        private RbacService $rbacService
    ) {}

    public function users()
    {
        return User::query()
            ->notArchived()
            ->select(['id', 'name', 'email', 'institution_id'])
            ->with(['roles:id,role,name', 'institution:id,name'])
            ->orderBy('id', 'desc')
            ->paginate(20);
    }

    public function syncUserRoles(SyncUserRolesRequest $request, User $user)
    {
        $roleIds = $request->validated('role_ids') ?? [];

        $error = $this->rbacService->validateSyncUserRoles($user, $roleIds);
        if ($error !== null) {
            return response()->json($error, 422);
        }

        $user->roles()->sync($roleIds);
        $this->auditLogService->logUserAction($request->user(), 'rbac.user.sync_roles', 'User', $user->id, 'success', null, ['user_id' => $user->id]);

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

        $role = UserRole::create($data);
        $this->auditLogService->logUserAction($request->user(), 'rbac.role.create', 'UserRole', $role->id, 'success');

        return $role;
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

        $group = UserRolePermissionGroup::create($data);
        $this->auditLogService->logUserAction($request->user(), 'rbac.group.create', 'UserRolePermissionGroup', $group->id, 'success');

        return $group;
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

        $permission = UserRolePermission::create($data);
        $this->auditLogService->logUserAction($request->user(), 'rbac.permission.create', 'UserRolePermission', $permission->id, 'success');

        return $permission;
    }

    public function syncRoleGroups(Request $request, UserRole $role)
    {
        $data = $request->validate([
            'group_ids' => ['array'],
            'group_ids.*' => ['uuid', 'exists:user_role_permission_group,id'],
        ]);

        $role->permissionGroups()->sync($data['group_ids'] ?? []);
        $this->auditLogService->logUserAction($request->user(), 'rbac.role.sync_groups', 'UserRole', $role->id, 'success');

        return $role->load('permissionGroups:id,name');
    }

    public function syncGroupPermissions(Request $request, UserRolePermissionGroup $group)
    {
        $data = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['uuid', 'exists:user_role_permission,id'],
        ]);

        $group->permissions()->sync($data['permission_ids'] ?? []);
        $this->auditLogService->logUserAction($request->user(), 'rbac.group.sync_permissions', 'UserRolePermissionGroup', $group->id, 'success');

        return $group->load('permissions:id,permission');
    }

    public function createUser(CreateUserRequest $request)
    {
        $data = $request->validated();
        $user = $this->rbacService->createUser($data);
        $this->auditLogService->logUserAction($request->user(), 'rbac.user.create', 'User', $user->id, 'success');

        return $user->load(['roles:id,role,name', 'institution:id,name']);
    }

    public function updateUser(UpdateUserRequest $request, User $user)
    {
        $this->rbacService->updateUser($user, $request->validated());
        $this->auditLogService->logUserAction($request->user(), 'rbac.user.update', 'User', $user->id, 'success');

        return $user->load(['roles:id,role,name', 'institution:id,name']);
    }

    public function deleteUser(Request $request, User $user)
    {
        if (auth()->id() === $user->id) {
            abort(422, 'Negalima ištrinti savo naudotojo.');
        }

        $user->update(['archived_at' => now()]);
        $this->auditLogService->logUserAction($request->user(), 'rbac.user.archive', 'User', $user->id, 'success');

        return response()->noContent();
    }

    public function deleteRole(Request $request, UserRole $role)
    {
        $roleId = $role->id;
        $role->users()->detach();
        $role->permissionGroups()->detach();
        $role->delete();
        $this->auditLogService->logUserAction($request->user(), 'rbac.role.delete', 'UserRole', $roleId, 'success');

        return response()->noContent();
    }

    public function deleteGroup(Request $request, UserRolePermissionGroup $group)
    {
        $groupId = $group->id;
        $group->roles()->detach();
        $group->permissions()->detach();
        $group->delete();
        $this->auditLogService->logUserAction($request->user(), 'rbac.group.delete', 'UserRolePermissionGroup', $groupId, 'success');

        return response()->noContent();
    }

    public function deletePermission(Request $request, UserRolePermission $permission)
    {
        $permissionId = $permission->id;
        $permission->groups()->detach();
        $permission->delete();
        $this->auditLogService->logUserAction($request->user(), 'rbac.permission.delete', 'UserRolePermission', $permissionId, 'success');

        return response()->noContent();
    }
}

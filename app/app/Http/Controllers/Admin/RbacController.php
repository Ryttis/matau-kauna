<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditLogResult;
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
            $msg = $error['message'] ?? json_encode($error);
            $this->auditLogService->logUserAction($request->user(), 'rbac.user.sync_roles', 'User', $user->id, AuditLogResult::Error, $msg, ['user_id' => $user->id]);
            return response()->json($error, 422);
        }

        try {
            $user->roles()->sync($roleIds);
            $this->auditLogService->logUserAction($request->user(), 'rbac.user.sync_roles', 'User', $user->id, AuditLogResult::Success, null, ['user_id' => $user->id]);
            return $user->load('roles:id,role,name');
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.user.sync_roles', 'User', $user->id, AuditLogResult::Error, $e->getMessage(), ['user_id' => $user->id]);
            throw $e;
        }
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

        try {
            $role = UserRole::create($data);
            $this->auditLogService->logUserAction($request->user(), 'rbac.role.create', 'UserRole', $role->id, AuditLogResult::Success);
            return $role;
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.role.create', 'UserRole', null, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
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

        try {
            $group = UserRolePermissionGroup::create($data);
            $this->auditLogService->logUserAction($request->user(), 'rbac.group.create', 'UserRolePermissionGroup', $group->id, AuditLogResult::Success);
            return $group;
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.group.create', 'UserRolePermissionGroup', null, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
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

        try {
            $permission = UserRolePermission::create($data);
            $this->auditLogService->logUserAction($request->user(), 'rbac.permission.create', 'UserRolePermission', $permission->id, AuditLogResult::Success);
            return $permission;
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.permission.create', 'UserRolePermission', null, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function syncRoleGroups(Request $request, UserRole $role)
    {
        $data = $request->validate([
            'group_ids' => ['array'],
            'group_ids.*' => ['uuid', 'exists:user_role_permission_group,id'],
        ]);

        try {
            $role->permissionGroups()->sync($data['group_ids'] ?? []);
            $this->auditLogService->logUserAction($request->user(), 'rbac.role.sync_groups', 'UserRole', $role->id, AuditLogResult::Success);
            return $role->load('permissionGroups:id,name');
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.role.sync_groups', 'UserRole', $role->id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function syncGroupPermissions(Request $request, UserRolePermissionGroup $group)
    {
        $data = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['uuid', 'exists:user_role_permission,id'],
        ]);

        try {
            $group->permissions()->sync($data['permission_ids'] ?? []);
            $this->auditLogService->logUserAction($request->user(), 'rbac.group.sync_permissions', 'UserRolePermissionGroup', $group->id, AuditLogResult::Success);
            return $group->load('permissions:id,permission');
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.group.sync_permissions', 'UserRolePermissionGroup', $group->id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function createUser(CreateUserRequest $request)
    {
        $data = $request->validated();
        try {
            $user = $this->rbacService->createUser($data);
            $this->auditLogService->logUserAction($request->user(), 'rbac.user.create', 'User', $user->id, AuditLogResult::Success);
            return $user->load(['roles:id,role,name', 'institution:id,name']);
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.user.create', 'User', null, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function updateUser(UpdateUserRequest $request, User $user)
    {
        try {
            $this->rbacService->updateUser($user, $request->validated());
            $this->auditLogService->logUserAction($request->user(), 'rbac.user.update', 'User', $user->id, AuditLogResult::Success);
            return $user->load(['roles:id,role,name', 'institution:id,name']);
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.user.update', 'User', $user->id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function deleteUser(Request $request, User $user)
    {
        if (auth()->id() === $user->id) {
            $msg = 'Negalima ištrinti savo naudotojo.';
            $this->auditLogService->logUserAction($request->user(), 'rbac.user.archive', 'User', $user->id, AuditLogResult::Error, $msg);
            abort(422, $msg);
        }

        try {
            $user->update(['archived_at' => now()]);
            $this->auditLogService->logUserAction($request->user(), 'rbac.user.archive', 'User', $user->id, AuditLogResult::Success);
            return response()->noContent();
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.user.archive', 'User', $user->id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function deleteRole(Request $request, UserRole $role)
    {
        $roleId = $role->id;
        try {
            $role->users()->detach();
            $role->permissionGroups()->detach();
            $role->delete();
            $this->auditLogService->logUserAction($request->user(), 'rbac.role.delete', 'UserRole', $roleId, AuditLogResult::Success);
            return response()->noContent();
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.role.delete', 'UserRole', $roleId, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function deleteGroup(Request $request, UserRolePermissionGroup $group)
    {
        $groupId = $group->id;
        try {
            $group->roles()->detach();
            $group->permissions()->detach();
            $group->delete();
            $this->auditLogService->logUserAction($request->user(), 'rbac.group.delete', 'UserRolePermissionGroup', $groupId, AuditLogResult::Success);
            return response()->noContent();
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.group.delete', 'UserRolePermissionGroup', $groupId, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function deletePermission(Request $request, UserRolePermission $permission)
    {
        $permissionId = $permission->id;
        try {
            $permission->groups()->detach();
            $permission->delete();
            $this->auditLogService->logUserAction($request->user(), 'rbac.permission.delete', 'UserRolePermission', $permissionId, AuditLogResult::Success);
            return response()->noContent();
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.permission.delete', 'UserRolePermission', $permissionId, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }
}

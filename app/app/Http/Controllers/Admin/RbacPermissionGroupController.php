<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Controller;
use App\Models\UserRolePermissionGroup;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class RbacPermissionGroupController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
    ) {}

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
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Controller;
use App\Models\UserRolePermission;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class RbacPermissionController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
    ) {}

    public function permissions()
    {
        return UserRolePermission::query()
            ->orderBy('name')
            ->orderBy('permission')
            ->get();
    }

    public function createPermission(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
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

    public function deletePermission(Request $request, UserRolePermission $permission)
    {
        $permissionId = $permission->id;
        try {
            $permission->roles()->detach();
            $permission->delete();
            $this->auditLogService->logUserAction($request->user(), 'rbac.permission.delete', 'UserRolePermission', $permissionId, AuditLogResult::Success);

            return response()->noContent();
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.permission.delete', 'UserRolePermission', $permissionId, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }
}

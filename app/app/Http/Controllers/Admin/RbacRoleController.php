<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Controller;
use App\Models\UserRole;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class RbacRoleController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
    ) {}

    public function roles()
    {
        return UserRole::query()
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

    public function deleteRole(Request $request, UserRole $role)
    {
        $roleId = $role->id;
        try {
            $role->users()->detach();
            $role->permissions()->detach();
            $role->delete();
            $this->auditLogService->logUserAction($request->user(), 'rbac.role.delete', 'UserRole', $roleId, AuditLogResult::Success);

            return response()->noContent();
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.role.delete', 'UserRole', $roleId, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\SyncUserRolesRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\RbacService;
use Illuminate\Http\Request;

class RbacUserController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
        private RbacService $rbacService,
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

        try {
            $user->roles()->sync($roleIds);
            $this->auditLogService->logUserAction($request->user(), 'rbac.user.sync_roles', 'User', $user->id, AuditLogResult::Success, null, ['user_id' => $user->id]);

            return $user->load('roles:id,role,name');
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'rbac.user.sync_roles', 'User', $user->id, AuditLogResult::Error, $e->getMessage(), ['user_id' => $user->id]);
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
}

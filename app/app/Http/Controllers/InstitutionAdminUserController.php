<?php

namespace App\Http\Controllers;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Concerns\ResolvesInstitutionAdminInstitutionId;
use App\Http\Requests\UpdateInstitutionUserRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\RbacService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class InstitutionAdminUserController extends Controller
{
    use ResolvesInstitutionAdminInstitutionId;

    public function __construct(
        private AuditLogService $auditLogService,
        private RbacService $rbacService,
    ) {}

    public function update(UpdateInstitutionUserRequest $request, User $user): JsonResponse
    {
        $actor = Auth::user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $this->ensureInstitutionAdminAccess($actor);
        $institutionId = $this->resolveInstitutionId($request, $actor);
        $this->ensureTargetInInstitution($user, $institutionId);

        try {
            $data = $request->validated();
            $data['institution_id'] = $user->institution_id;
            $this->rbacService->updateUser($user, $data);
            $this->auditLogService->logUserAction($actor, 'institution_admin.user.update', 'User', $user->id, AuditLogResult::Success);

            return response()->json($user->fresh()->load(['roles:id,role,name']));
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($actor, 'institution_admin.user.update', 'User', $user->id, AuditLogResult::Error, $e->getMessage());

            throw $e;
        }
    }

    private function ensureInstitutionAdminAccess(User $actor): void
    {
        if (! $actor->canManageTeams() && ! $actor->canManageInvitations()) {
            abort(403);
        }
    }

    private function ensureTargetInInstitution(User $target, int $institutionId): void
    {
        if ($target->archived_at !== null) {
            abort(404);
        }
        if ((int) $target->institution_id !== $institutionId) {
            abort(403);
        }
    }
}

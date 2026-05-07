<?php

namespace App\Http\Controllers;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Concerns\ResolvesInstitutionAdminInstitutionId;
use App\Http\Requests\UpdateInstitutionUserRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\RbacService;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InstitutionAdminUserController extends Controller
{
    use ResolvesInstitutionAdminInstitutionId;

    public function __construct(
        private AuditLogService $auditLogService,
        private RbacService $rbacService,
        private TwoFactorService $twoFactorService,
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

    public function resetTwoFactor(Request $request, User $user): JsonResponse
    {
        $actor = Auth::user();
        if (! $actor instanceof User) {
            abort(403);
        }
        $this->ensureInstitutionAdminAccess($actor);
        $institutionId = $this->resolveInstitutionId($request, $actor);
        $this->ensureTargetInInstitution($user, $institutionId);

        if (! $this->twoFactorService->isStaffUser($user)) {
            abort(422, '2FA galima tik vidiniams naudotojams.');
        }
        if (! (bool) $user->two_factor_enabled) {
            abort(422, '2FA turi būti įjungta.');
        }
        if (! filled($user->two_factor_secret)) {
            abort(422, 'Nerastas 2FA raktas.');
        }

        $user->update(['two_factor_confirmed_at' => null]);

        $this->auditLogService->logUserAction(
            $actor,
            'institution_admin.user.reset_two_factor',
            'User',
            $user->id,
            AuditLogResult::Success,
        );

        return response()->json([
            'ok' => true,
            'message' => '2FA patvirtinimas atšauktas. Naudotojas autentifikatorių turi nusistatyti iš naujo.',
        ]);
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

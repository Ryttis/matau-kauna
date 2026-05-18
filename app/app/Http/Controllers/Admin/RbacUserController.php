<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditLogResult;
use App\Enums\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\SyncUserRolesRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\RbacService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;

class RbacUserController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
        private RbacService $rbacService,
        private TwoFactorService $twoFactorService,
    ) {}

    public function users(Request $request)
    {
        $query = User::query()
            ->notArchived()
            ->select(['id', 'name', 'surname', 'email', 'institution_id', 'two_factor_enabled'])
            ->with(['roles:id,role,name', 'institution:id,name']);

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('surname', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereRaw("CONCAT_WS(' ', name, surname) like ?", [$like]);
            });
        }

        $roleId = $request->query('role_id');
        if ($roleId !== null && $roleId !== '') {
            $query->whereHas('roles', fn ($q) => $q->where('user_role.id', $roleId));
        }

        $institutionId = $request->query('institution_id');
        if ($institutionId === 'none') {
            $query->whereNull('institution_id');
        } elseif ($institutionId !== null && $institutionId !== '') {
            $query->where('institution_id', $institutionId);
        }

        return $query
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();
    }

    public function usersTwoFactor()
    {
        $globalRequired = $this->twoFactorService->isGloballyRequired();

        $users = User::query()
            ->notArchived()
            ->whereHas('roles', fn ($q) => $q->whereIn('role', UserRoleEnum::staffValues()))
            ->select(['id', 'name', 'email', 'institution_id', 'two_factor_enabled', 'two_factor_secret', 'two_factor_confirmed_at'])
            ->with(['roles:id,role,name', 'institution:id,name'])
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->through(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'institution' => $user->institution,
                    'roles' => $user->roles,
                    'two_factor_enabled' => $this->twoFactorService->isEffectivelyEnabled($user),
                    'two_factor_ready' => filled($user->two_factor_secret) && $user->two_factor_confirmed_at !== null,
                    'is_staff' => true,
                ];
            });

        return [
            'users' => $users,
            'global_required' => $globalRequired,
        ];
    }

    public function updateUserTwoFactor(Request $request, User $user)
    {
        $enabled = (bool) $request->validate([
            'enabled' => ['required', 'boolean'],
        ])['enabled'];

        if (! $this->twoFactorService->isStaffUser($user)) {
            abort(422, '2FA galima tik vidiniams naudotojams.');
        }

        if ($enabled) {
            $hadSecret = filled($user->two_factor_secret);
            $wasConfirmed = $user->two_factor_confirmed_at !== null;
            $secret = $this->twoFactorService->ensureSecret($user);
            $user->update(['two_factor_enabled' => true]);
            $freshUser = $user->fresh();
            $needsSetup = ! $hadSecret || ! $wasConfirmed || $freshUser?->two_factor_confirmed_at === null;

            return [
                'ok' => true,
                'message' => '2FA įjungta.',
                'needs_setup' => $needsSetup,
                'secret' => $needsSetup ? $secret : '',
                'otpauth_url' => $needsSetup && $freshUser ? $this->twoFactorService->otpauthUrl($freshUser) : '',
                'qr_image_url' => $needsSetup && $freshUser ? $this->twoFactorService->qrImageUrl($freshUser) : '',
            ];
        }

        if ($this->twoFactorService->isGloballyRequired()) {
            abort(422, 'Globalus 2FA ijungtas visiems vidiniams naudotojams.');
        }

        $user->update([
            'two_factor_enabled' => false,
        ]);

        return [
            'ok' => true,
            'message' => '2FA išjungta.',
        ];
    }

    public function resetUserTwoFactorConfirmation(User $user): array
    {
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

        return [
            'ok' => true,
            'message' => '2FA patvirtinimas atšauktas. Naudotojas autentifikatorių turi nusistatyti iš naujo.',
        ];
    }

    public function updateTwoFactorGlobal(Request $request)
    {
        $enabled = (bool) $request->validate([
            'enabled' => ['required', 'boolean'],
        ])['enabled'];

        $this->twoFactorService->setGlobalRequired($enabled);

        if ($enabled) {
            User::query()
                ->notArchived()
                ->whereHas('roles', fn ($q) => $q->whereIn('role', UserRoleEnum::staffValues()))
                ->each(function (User $user) {
                    $this->twoFactorService->ensureSecret($user);
                    $user->update([
                        'two_factor_enabled' => true,
                    ]);
                });
        } else {
            User::query()
                ->notArchived()
                ->whereHas('roles', fn ($q) => $q->whereIn('role', UserRoleEnum::staffValues()))
                ->update([
                    'two_factor_enabled' => false,
                ]);
        }

        return [
            'ok' => true,
            'message' => $enabled
                ? 'Globalus 2FA įjungtas visiems vidiniams naudotojams.'
                : 'Globalus 2FA išjungtas. 2FA nereikalingas niekam.',
        ];
    }

    public function disableTwoFactorForAllStaff()
    {
        if ($this->twoFactorService->isGloballyRequired()) {
            abort(422, 'Globalus 2FA ijungtas visiems vidiniams naudotojams.');
        }

        User::query()
            ->notArchived()
            ->whereHas('roles', fn ($q) => $q->whereIn('role', UserRoleEnum::staffValues()))
            ->update([
                'two_factor_enabled' => false,
            ]);

        return [
            'ok' => true,
            'message' => '2FA išjungta visiems vidiniams naudotojams.',
        ];
    }

    public function syncUserRoles(SyncUserRolesRequest $request, User $user)
    {
        $roleIds = array_values(array_unique($request->validated('role_ids')));

        try {
            $user->roles()->sync($roleIds);
            $this->twoFactorService->enforceGlobalRequirement($user);
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

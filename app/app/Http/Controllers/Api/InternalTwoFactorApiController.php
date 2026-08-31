<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalTwoFactorApiController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $users = User::query()
            ->notArchived()
            ->whereHas('roles', fn ($q) => $q->staff())
            ->select(['id', 'name', 'email', 'institution_id', 'two_factor_enabled', 'two_factor_secret', 'two_factor_confirmed_at'])
            ->with(['roles:id,role,name', 'institution:id,name'])
            ->orderBy('id', 'desc')
            ->paginate($perPage)
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

        return response()->json([
            'users' => $users,
            'global_required' => $this->twoFactorService->isGloballyRequired(),
        ]);
    }

    public function updateUser(Request $request, User $user): JsonResponse
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

            return response()->json([
                'ok' => true,
                'message' => '2FA įjungta.',
                'needs_setup' => $needsSetup,
                'secret' => $needsSetup ? $secret : '',
                'otpauth_url' => $needsSetup && $freshUser ? $this->twoFactorService->otpauthUrl($freshUser) : '',
                'qr_image_url' => $needsSetup && $freshUser ? $this->twoFactorService->qrImageUrl($freshUser) : '',
            ]);
        }

        if ($this->twoFactorService->isGloballyRequired()) {
            abort(422, 'Globalus 2FA ijungtas visiems vidiniams naudotojams.');
        }

        $user->update(['two_factor_enabled' => false]);

        return response()->json([
            'ok' => true,
            'message' => '2FA išjungta.',
        ]);
    }

    public function updateGlobal(Request $request): JsonResponse
    {
        $enabled = (bool) $request->validate([
            'enabled' => ['required', 'boolean'],
        ])['enabled'];

        $this->twoFactorService->setGlobalRequired($enabled);

        if ($enabled) {
            User::query()
                ->notArchived()
                ->whereHas('roles', fn ($q) => $q->staff())
                ->each(function (User $user) {
                    $this->twoFactorService->ensureSecret($user);
                    $user->update(['two_factor_enabled' => true]);
                });
        } else {
            User::query()
                ->notArchived()
                ->whereHas('roles', fn ($q) => $q->staff())
                ->update(['two_factor_enabled' => false]);
        }

        return response()->json([
            'ok' => true,
            'message' => $enabled
                ? 'Globalus 2FA įjungtas visiems vidiniams naudotojams.'
                : 'Globalus 2FA išjungtas. 2FA nereikalingas niekam.',
        ]);
    }

    public function disableAllStaff(): JsonResponse
    {
        if ($this->twoFactorService->isGloballyRequired()) {
            abort(422, 'Globalus 2FA ijungtas visiems vidiniams naudotojams.');
        }

        User::query()
            ->notArchived()
            ->whereHas('roles', fn ($q) => $q->staff())
            ->update(['two_factor_enabled' => false]);

        return response()->json([
            'ok' => true,
            'message' => '2FA išjungta visiems vidiniams naudotojams.',
        ]);
    }
}

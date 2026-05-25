<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoginLog;
use App\Services\ActiveRoleService;
use App\Services\AuditLogService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
        private TwoFactorService $twoFactorService,
        private ActiveRoleService $activeRoleService,
    ) {}

    public function create(): Response|RedirectResponse
    {
        if (request()->session()->has('two_factor_pending_user_id')) {
            return redirect()->route('two-factor.challenge');
        }

        $user = Auth::user();

        if ($user !== null && $user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();
        $this->twoFactorService->ensurePrepared($user);
        $user->refresh();
        if ($this->twoFactorService->requiresChallenge($user)) {
            Auth::logout();
            $request->session()->put('two_factor_pending_user_id', $user->id);
            $request->session()->put('two_factor_pending_remember', $request->boolean('remember'));
            $request->session()->regenerateToken();

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        $updates = ['last_login_at' => now(), 'inactive_reporter_reminder_sent_at' => null];
        if ($user->archived_at !== null) {
            $updates['archived_at'] = null;
        }
        $user->update($updates);
        LoginLog::create([
            'user_id' => $user->id,
            'event_type' => LoginLog::EVENT_LOGIN,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        $this->auditLogService->logLogin($user, $request);
        $activeRoleId = $this->activeRoleService->preferredActiveRoleIdForUser($user);
        if ($activeRoleId !== null) {
            $request->session()->put('active_role_id', $activeRoleId);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            $this->auditLogService->logLogout($user, $request);
        }
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}

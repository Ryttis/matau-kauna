<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoginLog;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function create(): Response|RedirectResponse
    {
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

        $request->session()->regenerate();

        $user = $request->user();
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
        $role = $user->roles()->first();
        if ($role) {
            $request->session()->put('active_role_id', $role->id);
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

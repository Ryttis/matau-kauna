<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService,
        private AuditLogService $auditLogService,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $showSetup = $user->two_factor_confirmed_at === null;

        return Inertia::render('Auth/TwoFactorChallenge', [
            'email' => $user->email,
            'show_setup' => $showSetup,
            'setup_secret' => $showSetup ? (string) $user->two_factor_secret : '',
            'setup_qr_image_url' => $showSetup ? $this->twoFactorService->qrImageUrl($user) : '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if (! $this->twoFactorService->verifyCode($user, $data['code'])) {
            return back()->withErrors([
                'code' => 'Neteisingas 2FA kodas.',
            ]);
        }

        if ($user->two_factor_confirmed_at === null) {
            $user->update(['two_factor_confirmed_at' => now()]);
        }

        $remember = (bool) $request->session()->pull('two_factor_pending_remember', false);
        $request->session()->forget('two_factor_pending_user_id');

        Auth::login($user, $remember);
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
        $role = $user->roles()->first();
        if ($role) {
            $request->session()->put('active_role_id', $role->id);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('two_factor_pending_user_id');
        $request->session()->forget('two_factor_pending_remember');

        return redirect()->route('login');
    }

    private function pendingUser(Request $request): ?User
    {
        $id = (int) $request->session()->get('two_factor_pending_user_id', 0);
        if ($id <= 0) {
            return null;
        }

        $user = User::query()->find($id);
        if (! $user || ! $this->twoFactorService->requiresChallenge($user)) {
            $request->session()->forget('two_factor_pending_user_id');
            $request->session()->forget('two_factor_pending_remember');

            return null;
        }

        return $user;
    }
}

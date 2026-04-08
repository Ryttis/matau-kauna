<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = User::query()->findOrFail($request->route('id'));

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if (Auth::check() && (int) Auth::id() !== (int) $user->getKey()) {
            abort(403);
        }

        if ($user->archived_at !== null) {
            $user->update(['archived_at' => null]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        if (! Auth::check()) {
            Auth::login($user, false);
            $request->session()->regenerate();
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}

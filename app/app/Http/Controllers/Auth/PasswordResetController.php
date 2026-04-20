<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $fill = [
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ];
                if ($user->viisp_personal_code_hash !== null) {
                    $fill['viisp_local_password_set_at'] = now();
                }
                $user->forceFill($fill)->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Slaptažodis pakeistas. Galite prisijungti.')
            : back()->withErrors(['email' => 'Nuoroda nebegalioja arba jau panaudota. Užsakykite naują.']);
    }
}

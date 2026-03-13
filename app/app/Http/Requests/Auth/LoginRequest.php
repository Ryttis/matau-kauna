<?php

namespace App\Http\Requests\Auth;

use App\Models\UserRole;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'login_type' => ['required', 'string', 'in:pareiskejas,darbuotojas'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();
        $loginType = $this->string('login_type')->toString();

        $staffRoles = [
            UserRole::ROLE_SYSTEM_ADMIN,
            UserRole::ROLE_PROBLEM_ADMIN,
            UserRole::ROLE_INSTITUTION_ADMIN,
            UserRole::ROLE_MUNICIPALITY_REP,
            UserRole::ROLE_EMPLOYEE,
        ];
        $hasStaffRole = collect($staffRoles)->contains(fn ($role) => $user->hasRole($role));

        $invalidReporter = $loginType === 'pareiskejas' && (! $user->hasRole(UserRole::ROLE_REPORTER) || $hasStaffRole);
        $invalidStaff = $loginType !== 'pareiskejas' && ! $hasStaffRole;

        if ($invalidReporter || $invalidStaff) {
            $this->failLogin();
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    private function failLogin(): never
    {
        Auth::logout();
        $this->session()->invalidate();
        $this->session()->regenerateToken();

        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }
}

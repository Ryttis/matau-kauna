<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRoleEnum;
use App\Models\User;
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

        $emailUser = User::query()->where('email', $this->input('email'))->first();
        if ($emailUser instanceof User && ! $emailUser->canAuthenticateWithEmailPassword()) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Prisijunkite per el. valdžios vartus arba nustatykite slaptažodį skiltyje Profilis.',
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => $this->authMessage('failed', 'Neteisingi prisijungimo duomenys.'),
            ]);
        }

        $user = Auth::user();
        $loginType = $this->string('login_type')->toString();

        $hasStaffRole = collect(UserRoleEnum::staffValues())->contains(fn ($role) => $user->hasRole($role));

        $invalidReporter = $loginType === 'pareiskejas' && (! $user->hasRole(UserRoleEnum::Reporter->value) || $hasStaffRole);
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
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ], 'lt'),
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
            'email' => $this->authMessage('failed', 'Neteisingi prisijungimo duomenys.'),
        ]);
    }

    private function authMessage(string $key, string $fallback): string
    {
        $translated = __("auth.{$key}", [], 'lt');

        return $translated === "auth.{$key}" ? $fallback : $translated;
    }
}

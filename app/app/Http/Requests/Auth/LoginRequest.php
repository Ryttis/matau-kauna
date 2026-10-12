<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Services\Auth\StaffCredentialAuthenticator;
use App\Services\Ldap\LdapAuthenticationException;
use App\Services\UserBlockingService;
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

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        // The `email` field carries the login identifier. Staff may submit a directory account
        // name instead of an email only while LDAP is enabled; everything else keeps requiring an email.
        $identifierRules = ['required', 'string', 'max:255'];
        if (! $this->staffAuthenticator()->acceptsDirectoryUsername($this->input('login_type'))) {
            $identifierRules[] = 'email';
        }

        return [
            'email' => $identifierRules,
            'password' => ['required', 'string'],
            'login_type' => ['required', 'string', 'in:pareiskejas,darbuotojas'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $loginType = $this->string('login_type')->toString();
        $identifier = $this->string('email')->toString();

        if ($this->staffAuthenticator()->appliesTo($loginType, $identifier)) {
            $this->authenticateWithDirectory($identifier);
        } else {
            $this->authenticateWithLocalPassword();
        }

        $user = Auth::user();
        if ($user->isBlocked()) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => UserBlockingService::BLOCKED_MESSAGE,
            ]);
        }

        $hasStaffRole = $user->hasStaffRole();

        $invalidReporter = $loginType === 'pareiskejas' && (! $user->hasAssignedRole(UserRoleEnum::Reporter->value) || $hasStaffRole);
        $invalidStaff = $loginType !== 'pareiskejas' && ! $hasStaffRole;

        if ($invalidReporter || $invalidStaff) {
            $this->failLogin();
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Existing local-password verification through the Eloquent session provider.
     */
    private function authenticateWithLocalPassword(): void
    {
        $emailUser = User::query()->whereRaw('LOWER(email) = ?', [$this->input('email')])->first();
        if ($emailUser instanceof User && ! $emailUser->canAuthenticateWithEmailPassword()) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Prisijunkite per el. valdžios vartus arba nustatykite slaptažodį skiltyje Profilis.',
            ]);
        }

        $credentials = $this->only('email', 'password');
        if ($emailUser instanceof User) {
            $credentials['email'] = $emailUser->email;
        }

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => $this->authMessage('failed', 'Neteisingi prisijungimo duomenys.'),
            ]);
        }
    }

    /**
     * LDAP credential verification for enrolled staff. Any directory failure (bad password,
     * outage, unmapped identity) is a generic login failure; the local password is never consulted.
     */
    private function authenticateWithDirectory(string $identifier): void
    {
        try {
            $user = $this->staffAuthenticator()->authenticate($identifier, (string) $this->input('password'));
        } catch (LdapAuthenticationException) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => $this->authMessage('failed', 'Neteisingi prisijungimo duomenys.'),
            ]);
        }

        Auth::login($user, $this->boolean('remember'));
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

    private function staffAuthenticator(): StaffCredentialAuthenticator
    {
        return $this->container->make(StaffCredentialAuthenticator::class);
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

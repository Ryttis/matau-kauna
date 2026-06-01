<?php

namespace App\Services;

use App\Enums\UserRoleEnum;
use App\Models\PasswordPolicy;
use App\Models\User;
use PragmaRX\Google2FALaravel\Google2FA;

class TwoFactorService
{
    public function __construct(
        private Google2FA $google2FA,
    ) {}

    public function isStaffUser(User $user): bool
    {
        return $user->roles()->whereIn('role', UserRoleEnum::staffValues())->exists();
    }

    public function requiresChallenge(User $user): bool
    {
        return $this->isStaffUser($user)
            && $this->isEffectivelyEnabled($user)
            && filled($user->two_factor_secret);
    }

    public function isEffectivelyEnabled(User $user): bool
    {
        return (bool) $user->two_factor_enabled
            || ($this->isGloballyRequired() && $this->isStaffUser($user));
    }

    public function ensureSecret(User $user): string
    {
        if (filled($user->two_factor_secret)) {
            return (string) $user->two_factor_secret;
        }

        $secret = $this->google2FA->generateSecretKey();
        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ]);

        return $secret;
    }

    public function ensurePrepared(User $user): void
    {
        if (! $this->isStaffUser($user)) {
            return;
        }

        if ($this->isGloballyRequired() && ! (bool) $user->two_factor_enabled) {
            $user->update(['two_factor_enabled' => true]);
            $user->refresh();
        }

        if ($this->isEffectivelyEnabled($user)) {
            $this->ensureSecret($user);
        }
    }

    public function enforceGlobalRequirement(User $user): void
    {
        $this->ensurePrepared($user);
    }

    public function verifyCode(User $user, string $code): bool
    {
        if (! filled($user->two_factor_secret)) {
            return false;
        }

        return (bool) $this->google2FA->verifyKey((string) $user->two_factor_secret, $code);
    }

    public function otpauthUrl(User $user): string
    {
        $secret = $this->ensureSecret($user);
        $company = 'Matau Kaune';

        return $this->google2FA->getQRCodeUrl($company, $user->email, $secret);
    }

    public function qrImageUrl(User $user): string
    {
        $otpauth = $this->otpauthUrl($user);

        return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data='.rawurlencode($otpauth);
    }

    public function isGloballyRequired(): bool
    {
        return (bool) PasswordPolicy::get()->two_factor_can_enable;
    }

    public function setGlobalRequired(bool $required): void
    {
        $row = PasswordPolicy::query()->first();
        if ($row) {
            $row->update(['two_factor_can_enable' => $required]);
        } else {
            PasswordPolicy::create([
                'min_length' => 8,
                'require_lowercase' => true,
                'require_uppercase' => true,
                'require_special' => true,
                'two_factor_can_enable' => $required,
                'prohibit_name_email' => true,
            ]);
        }
        PasswordPolicy::clearCache();
    }
}

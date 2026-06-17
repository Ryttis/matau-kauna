<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class TokenExpiration
{
    public const REMEMBER_SESSION_ABILITY = 'remember-session';

    public static function minutes(): int
    {
        return (int) config('session.lifetime');
    }

    public static function mobileDays(): int
    {
        return max(1, (int) config('kaunas.mobile_session_lifetime_days', 90));
    }

    public static function next(): Carbon
    {
        return now()->addMinutes(self::minutes());
    }

    public static function mobileNext(): Carbon
    {
        return now()->addDays(self::mobileDays());
    }

    public static function nextForRememberSession(bool $rememberSession): Carbon
    {
        return $rememberSession ? self::mobileNext() : self::next();
    }

    public static function abilitiesForRememberSession(bool $rememberSession): array
    {
        return $rememberSession ? ['*', self::REMEMBER_SESSION_ABILITY] : ['*'];
    }

    public static function tokenHasRememberSessionAbility(array $abilities): bool
    {
        return in_array(self::REMEMBER_SESSION_ABILITY, $abilities, true);
    }
}

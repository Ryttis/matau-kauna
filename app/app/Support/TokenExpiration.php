<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class TokenExpiration
{
    public static function minutes(): int
    {
        return (int) config('session.lifetime');
    }

    public static function next(): Carbon
    {
        return now()->addMinutes(self::minutes());
    }
}

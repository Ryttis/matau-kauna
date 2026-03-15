<?php

namespace App\Enums;

enum AuditLogResult: string
{
    case Success = 'success';
    case Error = 'error';
    case Failure = 'failure';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Sėkminga',
            self::Error => 'Klaida',
            self::Failure => 'Nepavyko',
        };
    }
}

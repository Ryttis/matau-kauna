<?php

namespace App\Enums;

enum AuditLogResult: string
{
    case Success = 'success';
    case Error = 'error';
    case Failure = 'failure';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Sėkminga',
            self::Error => 'Klaida',
            self::Failure => 'Nepavyko',
            self::Pending => 'Vykdoma',
        };
    }
}

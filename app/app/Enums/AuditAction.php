<?php

namespace App\Enums;

enum AuditAction: string
{
    case AUTH_LOGIN = 'auth.login';
    case AUTH_LOGOUT = 'auth.logout';
    case INTEGRATION_VIISP_LOGIN_INIT = 'integration.viisp.login_init';
    case INTEGRATION_VIISP_IDENTITY_EXCHANGE = 'integration.viisp.identity_exchange';
    case AUDIT_CONFIG_TOGGLE = 'audit_config.toggle';
    case AUDIT_CONFIG_UPDATE = 'audit_config.update';

    public static function labelFor(?string $action): string
    {
        if (! is_string($action) || trim($action) === '') {
            return '-';
        }

        $enum = self::tryFrom($action);
        if ($enum instanceof self) {
            return $enum->label();
        }

        return match (true) {
            str_starts_with($action, 'integration.') => 'Integracijos veiksmas',
            str_starts_with($action, 'automation.') => 'Automatizavimo veiksmas',
            str_starts_with($action, 'rbac.') => 'Prieigų valdymo veiksmas',
            str_starts_with($action, 'content_version.') => 'Turinio versijų veiksmas',
            default => $action,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AUTH_LOGIN => 'Prisijungimas',
            self::AUTH_LOGOUT => 'Atsijungimas',
            self::INTEGRATION_VIISP_LOGIN_INIT => 'VIISP prisijungimo inicijavimas',
            self::INTEGRATION_VIISP_IDENTITY_EXCHANGE => 'VIISP tapatybės duomenų gavimas',
            self::AUDIT_CONFIG_TOGGLE => 'Audito įjungimas / išjungimas',
            self::AUDIT_CONFIG_UPDATE => 'Audito nustatymų atnaujinimas',
        };
    }
}

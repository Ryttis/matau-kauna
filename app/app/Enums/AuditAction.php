<?php

namespace App\Enums;

enum AuditAction: string
{
    case AUTH_LOGIN = 'auth.login';
    case AUTH_LOGOUT = 'auth.logout';
    case INTEGRATION_VIISP_LOGIN_INIT = 'integration.viisp.login_init';
    case INTEGRATION_VIISP_IDENTITY_EXCHANGE = 'integration.viisp.identity_exchange';
    case INTEGRATION_DVS_SEND = 'integration.dvs.send';
    case INTEGRATION_DVS_PULL_STATUS = 'integration.dvs.pull_status';
    case INTEGRATION_DVS_FETCH_RUSYS = 'integration.dvs.fetch_rusys';
    case INTEGRATION_GIS_SEND = 'integration.gis.send';
    case INTEGRATION_GIS_PULL_STATUS = 'integration.gis.pull_status';
    case INTEGRATION_GIS_PULL_ATTACHMENTS = 'integration.gis.pull_attachments';
    case INTEGRATION_GIS_FETCH_METADATA = 'integration.gis.fetch_metadata';
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
            self::INTEGRATION_DVS_SEND => 'DVS duomenų siuntimas',
            self::INTEGRATION_DVS_PULL_STATUS => 'DVS duomenų gavimas',
            self::INTEGRATION_DVS_FETCH_RUSYS => 'DVS rūšių gavimas',
            self::INTEGRATION_GIS_SEND => 'GIS duomenų siuntimas',
            self::INTEGRATION_GIS_PULL_STATUS => 'GIS būsenos gavimas',
            self::INTEGRATION_GIS_PULL_ATTACHMENTS => 'GIS priedų gavimas',
            self::INTEGRATION_GIS_FETCH_METADATA => 'GIS laukų gavimas',
            self::AUDIT_CONFIG_TOGGLE => 'Audito įjungimas / išjungimas',
            self::AUDIT_CONFIG_UPDATE => 'Audito nustatymų atnaujinimas',
        };
    }
}

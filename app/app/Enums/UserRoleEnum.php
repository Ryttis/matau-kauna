<?php

namespace App\Enums;

enum UserRoleEnum: string
{
    case SystemAdmin = 'ROLE_SYSTEM_ADMIN';
    case ProblemAdmin = 'ROLE_PROBLEM_ADMIN';
    case InstitutionAdmin = 'ROLE_INSTITUTION_ADMIN';
    case MunicipalityRep = 'ROLE_MUNICIPALITY_REP';
    case Employee = 'ROLE_EMPLOYEE';
    case Reporter = 'ROLE_REPORTER';

    public static function staff(): array
    {
        return [
            self::SystemAdmin,
            self::ProblemAdmin,
            self::InstitutionAdmin,
            self::MunicipalityRep,
            self::Employee,
        ];
    }

    public static function staffValues(): array
    {
        return array_map(
            static fn (self $role) => $role->value,
            self::staff(),
        );
    }

    public static function staffPersonalDefaultOrder(): array
    {
        return [
            self::Employee,
            self::MunicipalityRep,
            self::InstitutionAdmin,
            self::ProblemAdmin,
            self::SystemAdmin,
        ];
    }
}

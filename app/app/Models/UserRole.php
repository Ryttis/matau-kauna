<?php

namespace App\Models;

use App\Enums\UserRoleEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserRole extends Model
{
    use HasUuids;

    protected $table = 'user_role';

    protected $fillable = ['role', 'name'];

    public const ROLE_SYSTEM_ADMIN = UserRoleEnum::SystemAdmin->value;

    public const ROLE_PROBLEM_ADMIN = UserRoleEnum::ProblemAdmin->value;

    public const ROLE_INSTITUTION_ADMIN = UserRoleEnum::InstitutionAdmin->value;

    public const ROLE_MUNICIPALITY_REP = UserRoleEnum::MunicipalityRep->value;

    public const ROLE_EMPLOYEE = UserRoleEnum::Employee->value;

    public const ROLE_REPORTER = UserRoleEnum::Reporter->value;

    public static function staffRoles(): array
    {
        return UserRoleEnum::staffValues();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            UserRolePermission::class,
            'user_role_permission_map',
            'role_id',
            'permission_id'
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_role_map',
            'role_id',
            'user_id'
        );
    }
}

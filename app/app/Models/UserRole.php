<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    use HasUuids;

    protected $table = 'user_role';

    protected $fillable = ['role', 'name'];

    public const ROLE_SYSTEM_ADMIN = 'ROLE_SYSTEM_ADMIN'; // Sistemos administratorius

    public const ROLE_PROBLEM_ADMIN = 'ROLE_PROBLEM_ADMIN'; // Problemų administratorius

    public const ROLE_INSTITUTION_ADMIN = 'ROLE_INSTITUTION_ADMIN'; // Įstaigos administratorius

    public const ROLE_MUNICIPALITY_REP = 'ROLE_MUNICIPALITY_REP'; // Savivaldybės atstovas

    public const ROLE_EMPLOYEE = 'ROLE_EMPLOYEE'; // Įstaigos darbuotojas

    public const ROLE_REPORTER = 'ROLE_REPORTER'; // Pareiškėjas (paprastas naudotojas)

    public function permissionGroups()
    {
        return $this->belongsToMany(
            UserRolePermissionGroup::class,
            'user_role_permission_group_role_map',
            'role_id',
            'group_id'
        );
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_role_map',
            'role_id',
            'user_id'
        );
    }
}

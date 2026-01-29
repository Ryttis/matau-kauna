<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UserRole extends Model
{
    use HasUuids;

    protected $table = 'user_role';

    protected $fillable = ['role', 'name'];

    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    public function permissionGroups()
    {
        return $this->belongsToMany(
            RolePermissionGroup::class,
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


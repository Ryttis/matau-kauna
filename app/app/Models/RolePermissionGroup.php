<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RolePermissionGroup extends Model
{
    use HasUuids;

    protected $table = 'user_role_permission_group';

    protected $fillable = ['name'];

    public function permissions()
    {
        return $this->belongsToMany(
            RolePermission::class,
            'user_role_permission_group_map',
            'group_id',
            'permission_id'
        );
    }

    public function roles()
    {
        return $this->belongsToMany(
            UserRole::class,
            'user_role_permission_group_role_map',
            'group_id',
            'role_id'
        );
    }
}

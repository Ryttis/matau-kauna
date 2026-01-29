<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    use HasUuids;

    protected $table = 'user_role_permission';

    protected $fillable = ['permission'];

    public function groups()
    {
        return $this->belongsToMany(
            RolePermissionGroup::class,
            'user_role_permission_group_map',
            'permission_id',
            'group_id'
        );
    }
}

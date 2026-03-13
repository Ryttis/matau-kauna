<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserRolePermission extends Model
{
    use HasUuids;

    protected $table = 'user_role_permission';

    protected $fillable = ['permission'];

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            UserRolePermissionGroup::class,
            'user_role_permission_group_map',
            'permission_id',
            'group_id'
        );
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserRolePermission extends Model
{
    use HasUuids;

    protected $table = 'user_role_permission';

    protected $fillable = ['name', 'permission'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            UserRole::class,
            'user_role_permission_map',
            'permission_id',
            'role_id'
        );
    }
}

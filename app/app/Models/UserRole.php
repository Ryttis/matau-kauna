<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserRole extends Model
{
    use HasUuids;

    protected $table = 'user_role';

    protected $fillable = ['role', 'name'];

    public const ROLE_SYSTEM_ADMIN = 'ROLE_SYSTEM_ADMIN';

    public const ROLE_PROBLEM_ADMIN = 'ROLE_PROBLEM_ADMIN';

    public const ROLE_INSTITUTION_ADMIN = 'ROLE_INSTITUTION_ADMIN';

    public const ROLE_MUNICIPALITY_REP = 'ROLE_MUNICIPALITY_REP';

    public const ROLE_EMPLOYEE = 'ROLE_EMPLOYEE';

    public const ROLE_REPORTER = 'ROLE_REPORTER';

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

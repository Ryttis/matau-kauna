<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'surname',
        'email',
        'password',
        'two_factor_enabled',
        'email_notifications_enabled',
        'email_updates_institution_admin',
        'institution_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'email_notifications_enabled' => 'boolean',
            'email_updates_institution_admin' => 'boolean',
        ];
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function roles()
    {
        return $this->belongsToMany(
            \App\Models\UserRole::class,
            'user_role_map',
            'user_id',
            'role_id'
        );
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('role', $role)->exists();
    }

    public function permissions(): array
    {
        $permissions = [];

        $roles = $this->roles()->with('permissionGroups.permissions')->get();

        foreach ($roles as $role) {
            foreach ($role->permissionGroups as $group) {
                foreach ($group->permissions as $permission) {
                    $permissions[$permission->permission] = $permission->permission;
                }
            }
        }

        return array_values($permissions);
    }

    public function permissionsForRole(string $roleId): array
    {
        $role = $this->roles()->with('permissionGroups.permissions')
            ->where('user_role.id', $roleId)
            ->first();

        if (! $role) {
            return [];
        }

        $permissions = [];
        foreach ($role->permissionGroups as $group) {
            foreach ($group->permissions as $permission) {
                $permissions[$permission->permission] = $permission->permission;
            }
        }

        return array_values($permissions);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}

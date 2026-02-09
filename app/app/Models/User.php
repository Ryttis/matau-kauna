<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'name',
        'surname',
        'email',
        'password',
        'two_factor_enabled',
        'email_notifications_enabled',
        'email_updates_institution_admin',
        'institution_id',
        'terms_accepted_at',
        'reporter_wizard_completed_at',
        'reporter_report_terms_accepted_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'reporter_wizard_completed_at' => 'datetime',
            'reporter_report_terms_accepted_at' => 'datetime',
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

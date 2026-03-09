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
        'archived_at',
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
            'archived_at' => 'datetime',
        ];
    }

    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
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

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function isProblemAdminOrSystemAdmin(): bool
    {
        return $this->hasAnyRole([
            \App\Models\UserRole::ROLE_PROBLEM_ADMIN,
            \App\Models\UserRole::ROLE_SYSTEM_ADMIN,
        ]);
    }

    public function isInstitutionAdminOrSystemAdmin(): bool
    {
        return $this->hasAnyRole([
            \App\Models\UserRole::ROLE_INSTITUTION_ADMIN,
            \App\Models\UserRole::ROLE_SYSTEM_ADMIN,
        ]);
    }

    public function canMergeProblems(): bool
    {
        return $this->hasAnyRole([
            \App\Models\UserRole::ROLE_PROBLEM_ADMIN,
            \App\Models\UserRole::ROLE_INSTITUTION_ADMIN,
            \App\Models\UserRole::ROLE_SYSTEM_ADMIN,
        ]);
    }

    public function canEditProblemClassification(): bool
    {
        if ($this->hasRole(\App\Models\UserRole::ROLE_SYSTEM_ADMIN)) {
            return true;
        }

        return $this->hasRole(\App\Models\UserRole::ROLE_PROBLEM_ADMIN)
            && $this->hasPermission('classification.edit');
    }

    public function isReporterOnly(): bool
    {
        return $this->hasRole(\App\Models\UserRole::ROLE_REPORTER)
            && ! $this->hasPermission('issues.view');
    }
}

<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

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
        'last_login_at',
        'inactive_reporter_reminder_sent_at',
        'reporter_email_own_problems',
        'reporter_email_watched_problems',
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
            'last_login_at' => 'datetime',
            'inactive_reporter_reminder_sent_at' => 'datetime',
            'reporter_email_own_problems' => 'boolean',
            'reporter_email_watched_problems' => 'boolean',
        ];
    }

    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeWantsInstitutionAdminAssignmentEmails($query)
    {
        return $query->where('email_updates_institution_admin', true);
    }

    public function scopeWantsEmployeeAssignmentEmails($query)
    {
        return $query->where('email_notifications_enabled', true);
    }

    public static function shouldReceiveInstitutionAssignmentEmail(string $email, int $institutionId): bool
    {
        $user = self::query()
            ->notArchived()
            ->where('email', $email)
            ->where('institution_id', $institutionId)
            ->with('roles:id,role')
            ->first(['id', 'email_updates_institution_admin', 'email_notifications_enabled']);

        if (! $user) {
            return true;
        }

        $isAdmin = $user->roles->contains('role', UserRole::ROLE_INSTITUTION_ADMIN);
        if ($isAdmin) {
            return $user->email_updates_institution_admin === true;
        }

        return $user->email_notifications_enabled === true;
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function watchedProblems(): BelongsToMany
    {
        return $this->belongsToMany(Problem::class, 'problem_watches')->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            UserRole::class,
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
            UserRole::ROLE_PROBLEM_ADMIN,
            UserRole::ROLE_SYSTEM_ADMIN,
        ]);
    }

    public function canPublishProblems(): bool
    {
        return $this->isProblemAdminOrSystemAdmin();
    }

    public function isInstitutionAdminOrSystemAdmin(): bool
    {
        return $this->hasAnyRole([
            UserRole::ROLE_INSTITUTION_ADMIN,
            UserRole::ROLE_SYSTEM_ADMIN,
        ]);
    }

    public function canMergeProblems(): bool
    {
        return $this->hasAnyRole([
            UserRole::ROLE_PROBLEM_ADMIN,
            UserRole::ROLE_INSTITUTION_ADMIN,
            UserRole::ROLE_SYSTEM_ADMIN,
        ]);
    }

    public function canEditProblemClassification(): bool
    {
        if ($this->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            return true;
        }

        return $this->hasRole(UserRole::ROLE_PROBLEM_ADMIN)
            && $this->hasPermission('classification.edit');
    }

    public function isReporterOnly(): bool
    {
        return $this->hasRole(UserRole::ROLE_REPORTER)
            && ! $this->hasPermission('issues.view');
    }
}

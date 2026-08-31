<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

    private ?string $activeRoleKeyBound = null;

    private ?array $activePermissionsBound = null;

    protected $fillable = [
        'name',
        'surname',
        'email',
        'phone',
        'viisp_personal_code_hash',
        'password',
        'viisp_local_password_set_at',
        'push_device_id',
        'push_fcm_token',
        'push_platform',
        'push_app_version',
        'push_registered_at',
        'push_notifications_enabled',
        'push_own_problems',
        'push_watched_problems',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'email_notifications_enabled',
        'email_updates_institution_admin',
        'institution_id',
        'active_role_id',
        'terms_accepted_at',
        'terms_accepted_version_id',
        'reporter_wizard_completed_at',
        'reporter_report_terms_accepted_at',
        'archived_at',
        'blocked_at',
        'blocked_by',
        'unblocked_at',
        'unblocked_by',
        'last_login_at',
        'inactive_reporter_reminder_sent_at',
        'reporter_email_own_problems',
        'reporter_email_watched_problems',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'reporter_wizard_completed_at' => 'datetime',
            'reporter_report_terms_accepted_at' => 'datetime',
            'password' => 'hashed',
            'viisp_local_password_set_at' => 'datetime',
            'push_registered_at' => 'datetime',
            'push_notifications_enabled' => 'boolean',
            'push_own_problems' => 'boolean',
            'push_watched_problems' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'email_notifications_enabled' => 'boolean',
            'email_updates_institution_admin' => 'boolean',
            'archived_at' => 'datetime',
            'blocked_at' => 'datetime',
            'unblocked_at' => 'datetime',
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

    public function routeNotificationForFcm(): ?string
    {
        return $this->push_fcm_token;
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

    public function acceptedTermsVersion(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'terms_accepted_version_id');
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'blocked_by');
    }

    public function unblockedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'unblocked_by');
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

    public function bindActiveRoleContext(?string $activeRoleKey, array $permissions): void
    {
        $this->activeRoleKeyBound = $activeRoleKey;
        $this->activePermissionsBound = $permissions;
    }

    public function hasAssignedRole(string $role): bool
    {
        return $this->roles()->where('role', $role)->exists();
    }

    public function hasRole(string $role): bool
    {
        if ($this->activeRoleKeyBound !== null) {
            return $this->activeRoleKeyBound === $role;
        }

        return $this->roles()->where('role', $role)->exists();
    }

    public function permissions(): array
    {
        if ($this->activePermissionsBound !== null) {
            return $this->activePermissionsBound;
        }

        $permissions = [];

        $roles = $this->roles()->with('permissions')->get();

        foreach ($roles as $role) {
            foreach ($role->permissions as $permission) {
                $permissions[$permission->permission] = $permission->permission;
            }
        }

        return array_values($permissions);
    }

    public function permissionsForRole(string $roleId): array
    {
        $role = $this->roles()->with('permissions')
            ->where('user_role.id', $roleId)
            ->first();

        if (! $role) {
            return [];
        }

        $permissions = [];
        foreach ($role->permissions as $permission) {
            $permissions[$permission->permission] = $permission->permission;
        }

        return array_values($permissions);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function sendEmailVerificationNotification(): void
    {
        if (EmailNotificationTemplate::isDisabled(EmailNotificationTemplate::KEY_VERIFY_EMAIL)) {
            return;
        }

        $this->notify(new VerifyEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        if (EmailNotificationTemplate::isDisabled(EmailNotificationTemplate::KEY_PASSWORD_RESET)) {
            return;
        }

        $this->notify(new ResetPassword($token));
    }

    public function hasAnyRole(array $roles): bool
    {
        if ($this->activeRoleKeyBound !== null) {
            return in_array($this->activeRoleKeyBound, $roles, true);
        }

        return $this->roles()->whereIn('role', $roles)->exists();
    }

    public function canAccessAdmin(): bool
    {
        return $this->hasPermission('admin.access');
    }

    public function canManageAutomation(): bool
    {
        return $this->hasPermission('automation.manage');
    }

    public function canManageInvitations(): bool
    {
        return $this->hasPermission('invitations.manage');
    }

    public function canManageTeams(): bool
    {
        return $this->hasPermission('teams.manage');
    }

    public function canAssignInstitutionProblems(): bool
    {
        return $this->hasPermission('issues.assign');
    }

    public function canReassignInstitutions(): bool
    {
        return $this->hasPermission('issues.reassign');
    }

    public function isProblemAdminOrSystemAdmin(): bool
    {
        return $this->hasPermission('issues.publish');
    }

    public function canPublishProblems(): bool
    {
        return $this->hasPermission('issues.publish');
    }

    public function isInstitutionAdminOrSystemAdmin(): bool
    {
        return $this->canManageInvitations();
    }

    public function canMergeProblems(): bool
    {
        return $this->hasPermission('issues.merge');
    }

    public function canDeleteProblems(): bool
    {
        return $this->hasPermission('issues.delete');
    }

    public function canEditProblemClassification(): bool
    {
        return $this->hasPermission('classification.edit');
    }

    /**
     * Ar naudotojas turi bent vieną vidinę rolę (bet kokią, išskyrus pareiškėją).
     */
    public function hasStaffRole(): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn (UserRole $role) => UserRole::isStaffRole($role->role));
        }

        return $this->roles()->staff()->exists();
    }

    public function isReporterOnly(): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('role', UserRole::ROLE_REPORTER)
                && ! $this->hasStaffRole();
        }

        return $this->roles()->where('role', UserRole::ROLE_REPORTER)->exists()
            && ! $this->hasStaffRole();
    }

    public function canAuthenticateWithEmailPassword(): bool
    {
        if ($this->viisp_personal_code_hash === null) {
            return true;
        }

        return $this->viisp_local_password_set_at !== null;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function canRequestPasswordResetLink(): bool
    {
        return $this->hasStaffRole();
    }

    public static function queryAssignableInstitutionEmployeeUsers(int $institutionId, ?string $exceptEmail = null): Builder
    {
        return self::query()
            ->notArchived()
            ->where('institution_id', $institutionId)
            ->whereHas('roles', fn ($q) => $q->where('role', UserRole::ROLE_EMPLOYEE))
            ->when($exceptEmail, fn ($q) => $q->where('email', '!=', $exceptEmail));
    }

    public function assignableInstitutionExcludedEmail(): ?string
    {
        return $this->hasAssignedRole(UserRole::ROLE_EMPLOYEE) ? null : $this->email;
    }
}

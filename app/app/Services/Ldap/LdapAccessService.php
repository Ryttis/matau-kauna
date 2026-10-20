<?php

namespace App\Services\Ldap;

use App\Enums\AuditLogResult;
use App\Models\LdapAccessRequest;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditLogService;
use App\Services\RbacService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LdapAccessService
{
    public function __construct(
        private LdapLinkService $links,
        private RbacService $rbac,
        private AuditLogService $audit,
    ) {}

    public function observe(LdapIdentity $identity, string $source): void
    {
        DB::transaction(function () use ($identity, $source) {
            $source = LdapIdentity::normalizeUsername($source);
            $username = $identity->normalizedUsername();
            $this->links->lockIdentity($source, $username);
            $request = LdapAccessRequest::firstOrCreate(
                ['ldap_source' => $source, 'ldap_username' => $username],
                ['first_seen_at' => now(), 'last_seen_at' => now()],
            );
            $request->update(['last_seen_at' => now(), 'directory_name' => $identity->displayName, 'directory_email' => $identity->email]);
            $this->record('observed', $request);
        });
    }

    public function approve(LdapAccessRequest $request, array $data, User $actor): User
    {
        return DB::transaction(function () use ($request, $data, $actor) {
            $this->links->lockIdentity($request->ldap_source, $request->ldap_username);
            $request = LdapAccessRequest::query()->lockForUpdate()->findOrFail($request->id);
            // Rejected requests are explicitly eligible for reconsideration.
            $this->ensureActionable($request);
            if (User::query()->whereLdapIdentity($request->ldap_source, $request->ldap_username)->exists()) {
                throw ValidationException::withMessages(['request' => 'LDAP paskyra jau susieta.']);
            }
            $roles = UserRole::query()->whereIn('id', $data['role_ids'])->get();
            if ($roles->count() !== count($data['role_ids']) || $roles->contains('role', UserRole::ROLE_REPORTER) || $roles->isEmpty()) {
                throw ValidationException::withMessages(['role_ids' => 'Pasirinkite vidines darbuotojo roles.']);
            }
            if ($roles->whereIn('role', [UserRole::ROLE_EMPLOYEE, UserRole::ROLE_INSTITUTION_ADMIN])->isNotEmpty() && empty($data['institution_id'])) {
                throw ValidationException::withMessages(['institution_id' => 'Pasirinktai rolei būtina įstaiga.']);
            }
            if (! empty($data['user_id'])) {
                $user = User::query()->notArchived()->lockForUpdate()->findOrFail($data['user_id']);
                if ($user->hasLdapIdentity()) {
                    throw ValidationException::withMessages(['user_id' => 'Naudotojas jau turi LDAP susiejimą.']);
                }
                $this->rbac->updateUser($user, [
                    'name' => $user->name, 'email' => $user->email, 'institution_id' => $data['institution_id'] ?? null,
                ]);
            } else {
                $user = $this->rbac->createUser([
                    'name' => $data['name'], 'surname' => $data['surname'] ?? null, 'email' => $data['email'],
                    'password' => Str::random(64), 'role_id' => $roles->first()->id,
                    'institution_id' => $data['institution_id'] ?? null,
                ], notify: false);
            }
            $this->rbac->syncRoles($user, $data['role_ids']);
            $this->links->link($user, $request->ldap_source, $request->ldap_username);
            $request->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'linked_user_id' => $user->id]);
            $this->record('approved', $request, $actor);

            return $user->fresh();
        });
    }

    public function reject(LdapAccessRequest $request, User $actor): void
    {
        DB::transaction(function () use ($request, $actor) {
            $this->links->lockIdentity($request->ldap_source, $request->ldap_username);
            $request = LdapAccessRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->ensureActionable($request);
            $request->update(['status' => 'rejected', 'rejected_by' => $actor->id, 'rejected_at' => now()]);
            $this->record('rejected', $request, $actor);
        });
    }

    private function ensureActionable(LdapAccessRequest $request): void
    {
        if (! in_array($request->status, ['pending', 'rejected'], true)) {
            throw ValidationException::withMessages(['request' => 'Prašymas jau patvirtintas.']);
        }
    }

    private function record(string $action, LdapAccessRequest $request, ?User $actor = null): void
    {
        $this->audit->logIntegrationEvent('auth.ldap.access_request.'.$action, 'ldap', [
            'ldap_source' => $request->ldap_source, 'ldap_username' => $request->ldap_username,
            'user_id' => $request->linked_user_id, 'admin_actor_id' => $actor?->id,
        ], AuditLogResult::Success, actor: $actor, subjectType: 'LdapAccessRequest', subjectId: $request->id);
    }
}

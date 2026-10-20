<?php

namespace App\Services\Ldap;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LdapLinkService
{
    // PostgreSQL is the application database. Serialize even when no mapping exists yet;
    // row locks alone cannot protect an absent identity. The unique index is a final guard.
    public function lockIdentity(string $source, string $username): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                LdapIdentity::normalizeUsername($source).'\0'.LdapIdentity::normalizeUsername($username),
            ]);
        }
    }

    public function link(User $user, string $source, string $username): void
    {
        DB::transaction(function () use ($user, $source, $username) {
            $source = LdapIdentity::normalizeUsername($source);
            $username = LdapIdentity::normalizeUsername($username);
            $this->lockIdentity($source, $username);
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($username === '' || $source === '' || ! $user->hasStaffRole()) {
                throw ValidationException::withMessages(['user_id' => 'Only users with an internal (staff) role can be linked.']);
            }
            $conflict = User::query()->whereLdapIdentity($source, $username)->whereKeyNot($user->id)->first();
            if ($conflict) {
                throw ValidationException::withMessages(['user_id' => sprintf('Directory account "%s" is already linked to user #%d.', $username, $conflict->id)]);
            }
            $user->forceFill(['ldap_source' => $source, 'ldap_username' => $username, 'ldap_linked_at' => now()])->save();
        });
    }
}

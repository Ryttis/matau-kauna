<?php

namespace App\Services\Ldap;

use App\Models\User;

/**
 * Maps a verified directory identity to an explicitly pre-provisioned MatauKauna user.
 *
 * Mapping policy (ADR-0001): the binding is the pair (users.ldap_source, users.ldap_username),
 * administratively linked in advance. Email, display name or any other directory attribute is
 * never used to find or create a user, and no user is created here.
 */
class LdapUserResolver
{
    public function __construct(
        private readonly LdapSettings $settings,
    ) {}

    /**
     * @throws LdapAuthenticationException
     */
    public function resolve(LdapIdentity $identity): User
    {
        $user = $this->findLinked($identity);
        if ($user === null) {
            throw LdapAuthenticationException::userMappingFailed(sprintf(
                'Directory identity "%s" (%s) is not linked to any MatauKauna user.',
                $identity->normalizedUsername(),
                $this->settings->source,
            ));
        }

        return $user;
    }

    public function findLinked(LdapIdentity $identity): ?User
    {
        $username = $identity->normalizedUsername();

        $candidates = User::query()
            ->whereLdapIdentity($this->settings->source, $username)
            ->limit(2)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() > 1) {
            throw LdapAuthenticationException::userMappingFailed(sprintf(
                'Directory identity "%s" (%s) is linked to more than one MatauKauna user.',
                $username,
                $this->settings->source,
            ));
        }

        return $candidates->first();
    }
}

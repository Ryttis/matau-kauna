<?php

namespace App\Services\Ldap;

use SensitiveParameter;

/**
 * Minimal directory transport used by the authenticator and diagnostics.
 * Implementations must never log or echo the password.
 */
interface LdapConnection
{
    /**
     * Simple bind with the given identity and password. An empty password is rejected
     * before contacting the server so an anonymous bind can never pass as a login.
     *
     * @throws LdapAuthenticationException
     */
    public function bind(string $identity, #[SensitiveParameter] string $password): void;

    /**
     * Anonymous bind, only for connectivity diagnostics (RootDSE).
     *
     * @throws LdapAuthenticationException
     */
    public function bindAnonymously(): void;

    /**
     * Subtree search returning a list of entries:
     * [['dn' => string, 'attributes' => ['lowercaseattr' => [string, ...]]], ...]
     *
     * @param  list<string>  $attributes
     * @return list<array{dn: string, attributes: array<string, list<string>>}>
     *
     * @throws LdapAuthenticationException
     */
    public function search(string $baseDn, string $filter, array $attributes): array;

    /**
     * Base-scope read of the RootDSE (empty DN).
     *
     * @param  list<string>  $attributes
     * @return array<string, list<string>>
     *
     * @throws LdapAuthenticationException
     */
    public function readRootDse(array $attributes): array;

    public function close(): void;
}

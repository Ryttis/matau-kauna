<?php

namespace App\Services\Ldap;

/**
 * Creates a fresh, unbound directory connection per authentication attempt or diagnostic.
 * Tests replace this binding with a fake directory; nothing else should construct connections.
 */
class LdapConnectionFactory
{
    public function __construct(
        private readonly LdapSettings $settings,
    ) {}

    public function create(): LdapConnection
    {
        return new ExtLdapConnection($this->settings);
    }
}

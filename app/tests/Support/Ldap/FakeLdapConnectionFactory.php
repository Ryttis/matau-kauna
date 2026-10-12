<?php

namespace Tests\Support\Ldap;

use App\Services\Ldap\LdapConnection;
use App\Services\Ldap\LdapConnectionFactory;

final class FakeLdapConnectionFactory extends LdapConnectionFactory
{
    public int $created = 0;

    public function __construct(
        public readonly FakeLdapConnection $connection,
    ) {
        // Deliberately not calling the parent: no settings/ext-ldap are needed for the fake.
    }

    public function create(): LdapConnection
    {
        $this->created++;

        return $this->connection;
    }
}

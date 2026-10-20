<?php

namespace Tests\Support\Ldap;

use App\Models\PasswordPolicy;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Ldap\LdapConnectionFactory;

trait InteractsWithFakeLdap
{
    protected ?FakeLdapConnection $fakeLdap = null;

    protected ?FakeLdapConnectionFactory $fakeLdapFactory = null;

    /**
     * Replace the ext-ldap factory with the in-memory directory. Automated tests never reach a real server.
     */
    protected function fakeLdap(): FakeLdapConnection
    {
        if ($this->fakeLdap === null) {
            $this->fakeLdap = new FakeLdapConnection;
            $this->fakeLdapFactory = new FakeLdapConnectionFactory($this->fakeLdap);
            $this->app->instance(LdapConnectionFactory::class, $this->fakeLdapFactory);
        }

        return $this->fakeLdap;
    }

    protected function enableLdap(bool $enabled = true): void
    {
        config([
            'ldap.enabled' => $enabled,
            'ldap.source' => 'test-ad',
            'ldap.uri' => 'ldaps://ldap.test.invalid:636',
            'ldap.base_dn' => 'CN=Users,DC=test,DC=invalid',
            'ldap.username_attribute' => 'sAMAccountName',
            'ldap.bind_template' => 'TEST\\{username}',
            'ldap.tls_verify' => true,
        ]);
        $this->fakeLdap();
    }

    /**
     * A directory account the fake accepts, with a matching single search entry.
     */
    protected function directoryAccount(string $username, string $password, ?string $mail = 'jonas@example.com'): void
    {
        $fake = $this->fakeLdap();
        $fake->accounts['TEST\\'.$username] = $password;
        $fake->entries = [FakeLdapConnection::entry($username, mail: $mail)];
    }

    protected function ldapStaffUser(string $username, array $attributes = [], array $roles = [UserRole::ROLE_EMPLOYEE]): User
    {
        $user = User::factory()->create(array_merge([
            'email' => $username.'@example.com',
            'ldap_source' => 'test-ad',
            'ldap_username' => $username,
            'ldap_linked_at' => now(),
        ], $attributes));

        $user->roles()->sync(collect($roles)->map(fn (string $role) => $this->role($role)->id)->all());

        return $user;
    }

    protected function role(string $role): UserRole
    {
        return UserRole::firstOrCreate(['role' => $role], ['name' => $role]);
    }

    protected function setGlobalTwoFactorRequired(bool $enabled): void
    {
        PasswordPolicy::query()->delete();
        PasswordPolicy::create([
            'min_length' => 8,
            'require_lowercase' => true,
            'require_uppercase' => true,
            'require_special' => true,
            'two_factor_can_enable' => $enabled,
            'prohibit_name_email' => true,
        ]);
        PasswordPolicy::clearCache();
    }
}

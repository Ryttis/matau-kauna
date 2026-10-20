<?php

namespace Tests\Feature\Console;

use App\Models\User;
use App\Models\UserRole;
use App\Services\Ldap\LdapAuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ldap\InteractsWithFakeLdap;
use Tests\TestCase;

class LdapCommandsTest extends TestCase
{
    use InteractsWithFakeLdap;
    use RefreshDatabase;

    public function test_ldap_check_prints_non_secret_configuration_and_root_dse(): void
    {
        $this->enableLdap();
        config(['ldap.tls_verify' => false, 'ldap.uri' => 'ldaps://ldap.test.invalid:636/?secret=should-not-matter']);
        $this->fakeLdap()->rootDse = [
            'defaultnamingcontext' => ['DC=test,DC=invalid'],
            'dnshostname' => ['dc1.test.invalid'],
            'supportedldapversion' => ['3', '2'],
        ];

        $this->artisan('ldap:check')
            ->expectsOutputToContain('ldaps://ldap.test.invalid:636')
            ->expectsOutputToContain('CN=Users,DC=test,DC=invalid')
            ->expectsOutputToContain('sAMAccountName')
            ->expectsOutputToContain('TEST\\{username}')
            ->expectsOutputToContain('certificate validation disabled')
            ->expectsOutputToContain('defaultNamingContext: DC=test,DC=invalid')
            ->expectsOutputToContain('Base DN is within')
            ->doesntExpectOutputToContain('should-not-matter')
            ->assertSuccessful();

        $this->assertSame(1, $this->fakeLdap()->anonymousBinds);
        $this->assertSame([], $this->fakeLdap()->bindCalls);
    }

    public function test_ldap_check_reports_unavailable_directory(): void
    {
        $this->enableLdap();
        $this->fakeLdap()->bindFailure = LdapAuthenticationException::unavailable("Can't contact LDAP server", 'connect');

        $this->artisan('ldap:check')
            ->expectsOutputToContain('ldap_unavailable')
            ->assertFailed();
    }

    public function test_link_user_binds_staff_account_without_changing_roles(): void
    {
        $this->enableLdap();
        $user = User::factory()->create(['email' => 'staff@example.com']);
        $user->roles()->sync([$this->role(UserRole::ROLE_EMPLOYEE)->id]);

        $this->artisan('ldap:link-user', ['user' => 'staff@example.com', 'username' => 'JDoe'])->assertSuccessful();

        $user->refresh();
        $this->assertSame('jdoe', $user->ldap_username);
        $this->assertSame('test-ad', $user->ldap_source);
        $this->assertNotNull($user->ldap_linked_at);
        $this->assertSame([UserRole::ROLE_EMPLOYEE], $user->roles->pluck('role')->all());

        $this->artisan('ldap:link-user', ['user' => (string) $user->id, '--unlink' => true])->assertSuccessful();
        $this->assertNull($user->fresh()->ldap_username);
    }

    public function test_link_user_refuses_reporter_only_accounts_and_duplicates(): void
    {
        $this->enableLdap();
        $reporter = User::factory()->create(['email' => 'citizen@example.com']);
        $reporter->roles()->sync([$this->role(UserRole::ROLE_REPORTER)->id]);
        $this->ldapStaffUser('jdoe', ['email' => 'first@example.com']);
        $second = User::factory()->create(['email' => 'second@example.com']);
        $second->roles()->sync([$this->role(UserRole::ROLE_EMPLOYEE)->id]);

        $this->artisan('ldap:link-user', ['user' => 'citizen@example.com', 'username' => 'citizen'])->assertFailed();
        $this->assertNull($reporter->fresh()->ldap_username);

        $this->artisan('ldap:link-user', ['user' => 'second@example.com', 'username' => 'JDOE'])->assertFailed();
        $this->assertNull($second->fresh()->ldap_username);

        $this->artisan('ldap:link-user', ['user' => 'missing@example.com', 'username' => 'x'])->assertFailed();
    }
}

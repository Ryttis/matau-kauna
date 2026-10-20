<?php

namespace Tests\Unit\Ldap;

use App\Services\Ldap\LdapAuthenticationException;
use App\Services\Ldap\LdapAuthenticator;
use App\Services\Ldap\LdapIdentity;
use App\Services\Ldap\LdapSettings;
use PHPUnit\Framework\TestCase;
use Tests\Support\Ldap\FakeLdapConnection;
use Tests\Support\Ldap\FakeLdapConnectionFactory;

class LdapAuthenticatorTest extends TestCase
{
    private const PASSWORD = 'Correct-Horse-Battery-9';

    private FakeLdapConnection $directory;

    private FakeLdapConnectionFactory $factory;

    private LdapAuthenticator $authenticator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = new FakeLdapConnection;
        $this->directory->accounts['TEST\\jdoe'] = self::PASSWORD;
        $this->directory->entries = [FakeLdapConnection::entry('jdoe', 'Jonas Jonaitis', 'jonas@example.com')];
        $this->factory = new FakeLdapConnectionFactory($this->directory);
        $this->authenticator = new LdapAuthenticator($this->settings(), $this->factory);
    }

    private function settings(): LdapSettings
    {
        return LdapSettings::fromConfig([
            'enabled' => true,
            'source' => 'test-ad',
            'uri' => 'ldaps://ldap.test.invalid:636',
            'base_dn' => 'CN=Users,DC=test,DC=invalid',
            'username_attribute' => 'sAMAccountName',
            'bind_template' => 'TEST\\{username}',
        ]);
    }

    private function expectReason(string $reason, callable $callback): LdapAuthenticationException
    {
        try {
            $callback();
        } catch (LdapAuthenticationException $e) {
            $this->assertSame($reason, $e->reason);

            return $e;
        }

        $this->fail('Expected LdapAuthenticationException with reason '.$reason);
    }

    public function test_successful_authentication_returns_normalized_identity(): void
    {
        $identity = $this->authenticator->authenticate('jdoe', self::PASSWORD);

        $this->assertInstanceOf(LdapIdentity::class, $identity);
        $this->assertSame('jdoe', $identity->username);
        $this->assertSame('jdoe', $identity->normalizedUsername());
        $this->assertSame('Jonas Jonaitis', $identity->displayName);
        $this->assertSame('jonas@example.com', $identity->email);
        $this->assertSame('CN=jdoe,CN=Users,DC=test,DC=invalid', $identity->distinguishedName);
    }

    public function test_exactly_one_bind_and_one_search_per_attempt(): void
    {
        $this->authenticator->authenticate('jdoe', self::PASSWORD);

        $this->assertSame(['TEST\\jdoe'], $this->directory->bindCalls);
        $this->assertSame(0, $this->directory->anonymousBinds);
        $this->assertCount(1, $this->directory->searchCalls);
        $this->assertSame(1, $this->factory->created);
        $this->assertSame(1, $this->directory->closeCalls);
    }

    public function test_search_uses_base_dn_escaped_filter_and_minimal_attributes(): void
    {
        $this->directory->accounts['TEST\\jo(n)as'] = self::PASSWORD;
        $this->directory->entries = [FakeLdapConnection::entry('jo(n)as')];

        $this->authenticator->authenticate('jo(n)as', self::PASSWORD);

        $call = $this->directory->searchCalls[0];
        $this->assertSame('CN=Users,DC=test,DC=invalid', $call['base_dn']);
        $this->assertSame('(&(objectClass=user)(sAMAccountName=jo\\28n\\29as))', $call['filter']);
        $this->assertSame(['sAMAccountName', 'displayName', 'mail', 'distinguishedName'], $call['attributes']);
        $this->assertNotContains('memberOf', $call['attributes']);
    }

    public function test_empty_password_is_rejected_before_any_directory_contact(): void
    {
        $e = $this->expectReason(LdapAuthenticationException::INVALID_CREDENTIALS, fn () => $this->authenticator->authenticate('jdoe', ''));

        $this->assertSame('validate', $e->operation);
        $this->assertSame(0, $this->factory->created);
        $this->assertSame([], $this->directory->bindCalls);
    }

    public function test_empty_username_is_rejected_before_any_directory_contact(): void
    {
        $this->expectReason(LdapAuthenticationException::INVALID_CREDENTIALS, fn () => $this->authenticator->authenticate('  ', self::PASSWORD));

        $this->assertSame(0, $this->factory->created);
    }

    public function test_username_that_could_alter_the_bind_identity_is_rejected_before_bind(): void
    {
        foreach (['jdoe@sav.kaunas.lt', 'OTHER\\jdoe', 'j doe', "jd\noe", 'jd*oe=x'] as $username) {
            $this->expectReason(LdapAuthenticationException::INVALID_CREDENTIALS, fn () => $this->authenticator->authenticate($username, self::PASSWORD));
        }

        $this->assertSame(0, $this->factory->created);
    }

    public function test_invalid_password_is_reported_as_invalid_credentials_with_ad_code(): void
    {
        $e = $this->expectReason(LdapAuthenticationException::INVALID_CREDENTIALS, fn () => $this->authenticator->authenticate('jdoe', 'wrong-password'));

        $this->assertSame('bind', $e->operation);
        $this->assertSame('52e', $e->diagnosticCode);
        $this->assertSame(['TEST\\jdoe'], $this->directory->bindCalls);
        $this->assertCount(0, $this->directory->searchCalls);
        $this->assertSame(1, $this->directory->closeCalls);
    }

    public function test_directory_outage_is_reported_as_unavailable(): void
    {
        $this->directory->bindFailure = LdapAuthenticationException::unavailable('Can\'t contact LDAP server', 'connect');

        $e = $this->expectReason(LdapAuthenticationException::UNAVAILABLE, fn () => $this->authenticator->authenticate('jdoe', self::PASSWORD));

        $this->assertSame('connect', $e->operation);
        $this->assertCount(0, $this->directory->searchCalls);
    }

    public function test_search_error_is_reported_as_search_failed(): void
    {
        $this->directory->searchFailure = LdapAuthenticationException::searchFailed('Operations error');

        $this->expectReason(LdapAuthenticationException::SEARCH_FAILED, fn () => $this->authenticator->authenticate('jdoe', self::PASSWORD));
    }

    public function test_zero_directory_matches_fail_closed(): void
    {
        $this->directory->entries = [];

        $this->expectReason(LdapAuthenticationException::IDENTITY_NOT_FOUND, fn () => $this->authenticator->authenticate('jdoe', self::PASSWORD));
    }

    public function test_multiple_directory_matches_fail_closed(): void
    {
        $this->directory->entries = [FakeLdapConnection::entry('jdoe'), FakeLdapConnection::entry('jdoe', dn: 'CN=jdoe2,CN=Users,DC=test,DC=invalid')];

        $this->expectReason(LdapAuthenticationException::IDENTITY_NOT_UNIQUE, fn () => $this->authenticator->authenticate('jdoe', self::PASSWORD));
    }

    public function test_entry_for_a_different_account_is_rejected(): void
    {
        $this->directory->entries = [FakeLdapConnection::entry('someone-else')];

        $this->expectReason(LdapAuthenticationException::IDENTITY_NOT_FOUND, fn () => $this->authenticator->authenticate('jdoe', self::PASSWORD));
    }

    public function test_password_never_appears_in_failure_messages(): void
    {
        $secret = 'Super-Secret-Pass-42!';

        foreach ([
            fn () => $this->authenticator->authenticate('jdoe', $secret),
            fn () => $this->authenticator->authenticate('bad@name', $secret),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected failure');
            } catch (LdapAuthenticationException $e) {
                $this->assertStringNotContainsString($secret, $e->getMessage());
                $this->assertStringNotContainsString($secret, (string) $e);
            }
        }

        $this->assertStringNotContainsString($secret, json_encode($this->directory->bindCalls));
    }
}

<?php

namespace Tests\Unit\Ldap;

use App\Services\Ldap\LdapSettings;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LdapSettingsTest extends TestCase
{
    private function settings(array $overrides = []): LdapSettings
    {
        return LdapSettings::fromConfig(array_merge([
            'enabled' => true,
            'source' => 'test-ad',
            'uri' => 'ldaps://ldap.test.invalid:636',
            'base_dn' => 'CN=Users,DC=test,DC=invalid',
            'username_attribute' => 'sAMAccountName',
            'bind_template' => 'TEST\\{username}',
            'version' => 3,
            'referrals' => false,
            'tls_verify' => true,
            'network_timeout' => 10,
        ], $overrides));
    }

    public function test_bind_identity_uses_configured_template(): void
    {
        $this->assertSame('TEST\\jdoe', $this->settings()->bindIdentity('jdoe'));
        $this->assertSame('jdoe', $this->settings(['bind_template' => '{username}'])->bindIdentity('jdoe'));
    }

    public function test_bind_template_must_contain_placeholder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->settings(['bind_template' => 'CENTRAS\\user']);
    }

    public function test_username_attribute_must_be_plain_attribute_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->settings(['username_attribute' => 'sAMAccountName)(objectClass=*']);
    }

    public function test_search_filter_escapes_user_input(): void
    {
        $filter = $this->settings()->searchFilter('jo(n)as*\\x'."\0");

        $this->assertSame('(&(objectClass=user)(sAMAccountName=jo\\28n\\29as\\2a\\5cx\\00))', $filter);
    }

    public function test_escape_matches_ext_ldap_when_available(): void
    {
        if (! function_exists('ldap_escape')) {
            $this->markTestSkipped('ext-ldap not loaded');
        }

        $value = 'a(b)c*d\\e';
        $this->assertSame(ldap_escape($value, '', LDAP_ESCAPE_FILTER), LdapSettings::escapeFilterValue($value));
    }

    public function test_safe_uri_drops_everything_but_scheme_host_port(): void
    {
        $this->assertSame('ldaps://ldap.test.invalid:636', $this->settings()->safeUri());
        $this->assertSame('(unparseable LDAP_URI)', $this->settings(['uri' => ''])->safeUri());
    }

    public function test_tls_verification_is_configuration_driven(): void
    {
        $this->assertTrue($this->settings(['tls_verify' => true])->tlsVerify);
        $this->assertFalse($this->settings(['tls_verify' => false])->tlsVerify);
    }
}

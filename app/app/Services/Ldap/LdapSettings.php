<?php

namespace App\Services\Ldap;

use InvalidArgumentException;

/**
 * Immutable LDAP connection and lookup settings built from config/ldap.php.
 */
final class LdapSettings
{
    public const USERNAME_PLACEHOLDER = '{username}';

    public function __construct(
        public readonly bool $enabled,
        public readonly string $source,
        public readonly string $uri,
        public readonly string $baseDn,
        public readonly string $usernameAttribute,
        public readonly string $bindTemplate,
        public readonly int $version,
        public readonly bool $referrals,
        public readonly bool $tlsVerify,
        public readonly int $networkTimeout,
    ) {
        if (! str_contains($bindTemplate, self::USERNAME_PLACEHOLDER)) {
            throw new InvalidArgumentException('LDAP_BIND_TEMPLATE must contain the {username} placeholder.');
        }

        // The attribute name is interpolated into the search filter unescaped.
        if (preg_match('/^[A-Za-z][A-Za-z0-9-]*$/', $usernameAttribute) !== 1) {
            throw new InvalidArgumentException('LDAP_USERNAME_ATTRIBUTE must be a plain LDAP attribute name.');
        }

        if ($source === '') {
            throw new InvalidArgumentException('LDAP_SOURCE must not be empty.');
        }
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            enabled: (bool) ($config['enabled'] ?? false),
            source: (string) ($config['source'] ?? 'kaunas-ad'),
            uri: (string) ($config['uri'] ?? ''),
            baseDn: (string) ($config['base_dn'] ?? ''),
            usernameAttribute: (string) ($config['username_attribute'] ?? 'sAMAccountName'),
            bindTemplate: (string) ($config['bind_template'] ?? self::USERNAME_PLACEHOLDER),
            version: (int) ($config['version'] ?? 3),
            referrals: (bool) ($config['referrals'] ?? false),
            tlsVerify: (bool) ($config['tls_verify'] ?? true),
            networkTimeout: max(1, (int) ($config['network_timeout'] ?? 10)),
        );
    }

    public function bindIdentity(string $username): string
    {
        return str_replace(self::USERNAME_PLACEHOLDER, $username, $this->bindTemplate);
    }

    /**
     * Search filter for the directory user that just proved its password.
     * User input is escaped for LDAP filter syntax (RFC 4515).
     */
    public function searchFilter(string $username): string
    {
        return sprintf(
            '(&(objectClass=user)(%s=%s))',
            $this->usernameAttribute,
            self::escapeFilterValue($username),
        );
    }

    /**
     * URI reduced to scheme, host and port - safe for logs and console output.
     */
    public function safeUri(): string
    {
        $parts = parse_url($this->uri);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return '(unparseable LDAP_URI)';
        }

        return sprintf(
            '%s://%s%s',
            $parts['scheme'] ?? 'ldap',
            $parts['host'],
            isset($parts['port']) ? ':'.$parts['port'] : '',
        );
    }

    /**
     * RFC 4515 escaping. Equivalent to ldap_escape($value, '', LDAP_ESCAPE_FILTER)
     * but independent of ext-ldap so settings/filters are testable without it.
     */
    public static function escapeFilterValue(string $value): string
    {
        return strtr($value, [
            '\\' => '\5c',
            '*' => '\2a',
            '(' => '\28',
            ')' => '\29',
            "\0" => '\00',
        ]);
    }
}

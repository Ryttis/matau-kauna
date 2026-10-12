<?php

namespace App\Services\Ldap;

use LDAP\Connection as LdapResource;
use SensitiveParameter;

/**
 * Thin wrapper over ext-ldap. One instance = one directory connection; it is created
 * per login attempt and closed afterwards. Classifies failures into
 * LdapAuthenticationException reasons without ever exposing the password.
 */
final class ExtLdapConnection implements LdapConnection
{
    private const LDAP_INVALID_CREDENTIALS = 49;

    private ?LdapResource $resource = null;

    public function __construct(
        private readonly LdapSettings $settings,
    ) {}

    public function bind(string $identity, #[SensitiveParameter] string $password): void
    {
        if ($identity === '' || $password === '') {
            throw LdapAuthenticationException::invalidCredentials('Refusing to bind with an empty identity or password.');
        }

        $resource = $this->connect();

        if (@ldap_bind($resource, $identity, $password) === false) {
            $this->throwFromLastError($resource, 'bind', sprintf('LDAP bind as "%s" to %s failed', $identity, $this->settings->safeUri()));
        }
    }

    public function bindAnonymously(): void
    {
        $resource = $this->connect();

        if (@ldap_bind($resource) === false) {
            $this->throwFromLastError($resource, 'anonymous_bind', sprintf('Anonymous LDAP bind to %s failed', $this->settings->safeUri()));
        }
    }

    public function search(string $baseDn, string $filter, array $attributes): array
    {
        $resource = $this->connect();

        $result = @ldap_search($resource, $baseDn, $filter, $attributes, 0, 0, $this->settings->networkTimeout);
        if ($result === false) {
            $this->throwFromLastError($resource, 'search', sprintf('LDAP search under "%s" failed', $baseDn));
        }

        $entries = @ldap_get_entries($resource, $result);
        if ($entries === false) {
            $this->throwFromLastError($resource, 'search', 'LDAP search result could not be read');
        }

        $normalized = [];
        $count = (int) ($entries['count'] ?? 0);
        for ($i = 0; $i < $count; $i++) {
            $normalized[] = $this->normalizeEntry($entries[$i]);
        }

        return $normalized;
    }

    public function readRootDse(array $attributes): array
    {
        $resource = $this->connect();

        $result = @ldap_read($resource, '', '(objectClass=*)', $attributes, 0, 0, $this->settings->networkTimeout);
        if ($result === false) {
            $this->throwFromLastError($resource, 'rootdse', sprintf('RootDSE read from %s failed', $this->settings->safeUri()));
        }

        $entries = @ldap_get_entries($resource, $result);
        if ($entries === false || (int) ($entries['count'] ?? 0) < 1) {
            return [];
        }

        return $this->normalizeEntry($entries[0])['attributes'];
    }

    public function close(): void
    {
        if ($this->resource !== null) {
            @ldap_unbind($this->resource);
            $this->resource = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function connect(): LdapResource
    {
        if ($this->resource !== null) {
            return $this->resource;
        }

        if (! function_exists('ldap_connect')) {
            throw LdapAuthenticationException::unavailable('PHP ext-ldap is not loaded; LDAP authentication is not possible.');
        }

        // Certificate verification must be configured on the global context before the
        // connection's TLS session is created (ldaps:// handshakes on first operation).
        $requireCert = $this->settings->tlsVerify ? LDAP_OPT_X_TLS_DEMAND : LDAP_OPT_X_TLS_NEVER;
        @ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, $requireCert);

        $resource = @ldap_connect($this->settings->uri);
        if ($resource === false) {
            throw LdapAuthenticationException::unavailable(sprintf('Invalid LDAP_URI (%s).', $this->settings->safeUri()));
        }

        @ldap_set_option($resource, LDAP_OPT_PROTOCOL_VERSION, $this->settings->version);
        @ldap_set_option($resource, LDAP_OPT_REFERRALS, $this->settings->referrals ? 1 : 0);
        @ldap_set_option($resource, LDAP_OPT_NETWORK_TIMEOUT, $this->settings->networkTimeout);
        if (defined('LDAP_OPT_TIMEOUT')) {
            @ldap_set_option($resource, LDAP_OPT_TIMEOUT, $this->settings->networkTimeout);
        }
        @ldap_set_option($resource, LDAP_OPT_X_TLS_REQUIRE_CERT, $requireCert);
        if (defined('LDAP_OPT_X_TLS_NEWCTX')) {
            // Apply the per-connection TLS options to a fresh TLS context.
            @ldap_set_option($resource, LDAP_OPT_X_TLS_NEWCTX, 0);
        }

        return $this->resource = $resource;
    }

    /**
     * @return array{dn: string, attributes: array<string, list<string>>}
     */
    private function normalizeEntry(array $entry): array
    {
        $attributes = [];
        $attributeCount = (int) ($entry['count'] ?? 0);
        for ($i = 0; $i < $attributeCount; $i++) {
            $name = $entry[$i] ?? null;
            if (! is_string($name) || ! isset($entry[$name]) || ! is_array($entry[$name])) {
                continue;
            }
            $values = [];
            $valueCount = (int) ($entry[$name]['count'] ?? 0);
            for ($j = 0; $j < $valueCount; $j++) {
                $values[] = (string) $entry[$name][$j];
            }
            $attributes[strtolower($name)] = $values;
        }

        return [
            'dn' => (string) ($entry['dn'] ?? ''),
            'attributes' => $attributes,
        ];
    }

    private function throwFromLastError(LdapResource $resource, string $operation, string $context): never
    {
        $errno = ldap_errno($resource);
        $error = ldap_error($resource);
        $diagnostic = null;
        if (defined('LDAP_OPT_DIAGNOSTIC_MESSAGE')) {
            @ldap_get_option($resource, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagnostic);
        }
        $diagnosticCode = self::activeDirectoryDataCode(is_string($diagnostic) ? $diagnostic : null);
        $message = sprintf('%s: [%d] %s', $context, $errno, $error);
        if ($diagnosticCode !== null) {
            $message .= sprintf(' (AD data %s)', $diagnosticCode);
        }

        if ($errno === self::LDAP_INVALID_CREDENTIALS) {
            throw LdapAuthenticationException::invalidCredentials($message, $operation, $diagnosticCode);
        }

        if ($operation === 'search') {
            throw LdapAuthenticationException::searchFailed($message, $diagnosticCode);
        }

        throw LdapAuthenticationException::unavailable($message, $operation, $diagnosticCode);
    }

    /**
     * Extracts the Active Directory sub-code from a diagnostic such as
     * "80090308: LdapErr: DSID-0C09044E, comment: AcceptSecurityContext error, data 52e, v4563".
     * (52e = bad password, 525 = no such user, 533 = disabled, 775 = locked out, 701/773 = expired.)
     */
    public static function activeDirectoryDataCode(?string $diagnostic): ?string
    {
        if ($diagnostic === null || preg_match('/\bdata\s+([0-9a-fA-F]+)\b/', $diagnostic, $m) !== 1) {
            return null;
        }

        return strtolower($m[1]);
    }
}

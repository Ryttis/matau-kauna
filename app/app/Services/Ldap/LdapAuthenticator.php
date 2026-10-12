<?php

namespace App\Services\Ldap;

use SensitiveParameter;

/**
 * Verifies a directory username/password with exactly one LDAP bind, then reads the bound
 * user's own entry and returns a normalized identity. Knows nothing about application users.
 */
class LdapAuthenticator
{
    /** Attributes requested from the directory; nothing else is read. */
    public const ATTRIBUTES = ['sAMAccountName', 'displayName', 'mail', 'distinguishedName'];

    // Characters Active Directory forbids in sAMAccountName, plus whitespace, '@' and control
    // characters. Rejected locally before any bind so they can never alter the bind identity.
    private const FORBIDDEN_USERNAME_PATTERN = '/["\/\\\\\[\]:;|=,+*?<>@\s\x00-\x1F\x7F]/';

    private const MAX_USERNAME_LENGTH = 255;

    public function __construct(
        private readonly LdapSettings $settings,
        private readonly LdapConnectionFactory $connections,
    ) {}

    /**
     * @throws LdapAuthenticationException
     */
    public function authenticate(string $username, #[SensitiveParameter] string $password): LdapIdentity
    {
        $username = trim($username);

        // An empty password would be an unauthenticated (anonymous) bind, which the directory
        // accepts without verifying anything. Reject locally before any network contact.
        if ($username === '' || $password === '') {
            throw LdapAuthenticationException::invalidCredentials('Empty username or password rejected before bind.', 'validate');
        }

        if (strlen($username) > self::MAX_USERNAME_LENGTH || preg_match(self::FORBIDDEN_USERNAME_PATTERN, $username) === 1) {
            throw LdapAuthenticationException::invalidCredentials('Username contains characters not allowed in a directory account name.', 'validate');
        }

        $connection = $this->connections->create();

        try {
            $connection->bind($this->settings->bindIdentity($username), $password);

            $entries = $connection->search(
                $this->settings->baseDn,
                $this->settings->searchFilter($username),
                self::ATTRIBUTES,
            );
        } finally {
            $connection->close();
        }

        $count = count($entries);
        if ($count === 0) {
            throw LdapAuthenticationException::identityNotFound(sprintf(
                'Bind succeeded but no entry matched %s under "%s".',
                $this->settings->usernameAttribute,
                $this->settings->baseDn,
            ));
        }
        if ($count > 1) {
            throw LdapAuthenticationException::identityNotUnique(sprintf(
                'Directory search returned %d entries for one %s, expected exactly 1.',
                $count,
                $this->settings->usernameAttribute,
            ));
        }

        return $this->toIdentity($entries[0], $username);
    }

    /**
     * @param  array{dn: string, attributes: array<string, list<string>>}  $entry
     */
    private function toIdentity(array $entry, string $requestedUsername): LdapIdentity
    {
        $attributes = $entry['attributes'];
        $usernameAttribute = strtolower($this->settings->usernameAttribute);

        $username = $this->first($attributes, $usernameAttribute);
        if ($username === null) {
            throw LdapAuthenticationException::searchFailed(sprintf('Directory entry is missing the %s attribute.', $this->settings->usernameAttribute));
        }

        // The entry must be the account that bound; a filter that somehow matched another
        // account (e.g. attribute aliasing) must not authenticate it.
        if (LdapIdentity::normalizeUsername($username) !== LdapIdentity::normalizeUsername($requestedUsername)) {
            throw LdapAuthenticationException::identityNotFound('Directory entry does not match the authenticated username.');
        }

        return new LdapIdentity(
            username: $username,
            distinguishedName: $this->first($attributes, 'distinguishedname') ?? $entry['dn'],
            displayName: $this->first($attributes, 'displayname'),
            email: $this->first($attributes, 'mail'),
        );
    }

    /**
     * @param  array<string, list<string>>  $attributes
     */
    private function first(array $attributes, string $name): ?string
    {
        $values = $attributes[$name] ?? [];
        $value = $values[0] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}

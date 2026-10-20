<?php

namespace App\Services\Ldap;

/**
 * Normalized directory identity of a user who proved their password via LDAP bind.
 * Contains only what the MatauKauna mapping layer needs; raw LDAP records stay inside the client.
 */
final class LdapIdentity
{
    public function __construct(
        public readonly string $username,
        public readonly string $distinguishedName,
        public readonly ?string $displayName = null,
        public readonly ?string $email = null,
    ) {}

    /**
     * Normalized, case-insensitive form of the directory login name used for mapping.
     */
    public function normalizedUsername(): string
    {
        return self::normalizeUsername($this->username);
    }

    public static function normalizeUsername(string $username): string
    {
        return mb_strtolower(trim($username));
    }
}

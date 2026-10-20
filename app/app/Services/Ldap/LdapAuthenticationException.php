<?php

namespace App\Services\Ldap;

use RuntimeException;

/**
 * Internal LDAP / staff-credential failure. The reason and message are for logs and
 * diagnostics only. Only the fixed APPROVAL_MESSAGE may be shown to the end user.
 */
final class LdapAuthenticationException extends RuntimeException
{
    public const APPROVAL_PENDING = 'approval_pending';

    public const APPROVAL_MESSAGE = 'Paskyra laukia administratoriaus patvirtinimo.';

    public static function approvalPending(): self
    {
        return new self(self::APPROVAL_PENDING, 'map_user', self::APPROVAL_MESSAGE);
    }

    public const INVALID_CREDENTIALS = 'invalid_credentials';

    public const UNAVAILABLE = 'ldap_unavailable';

    public const SEARCH_FAILED = 'search_failed';

    public const IDENTITY_NOT_FOUND = 'identity_not_found';

    public const IDENTITY_NOT_UNIQUE = 'identity_not_unique';

    public const USER_MAPPING_FAILED = 'user_mapping_failed';

    private function __construct(
        public readonly string $reason,
        public readonly string $operation,
        string $message,
        public readonly ?string $diagnosticCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function invalidCredentials(string $message, string $operation = 'bind', ?string $diagnosticCode = null, ?\Throwable $previous = null): self
    {
        return new self(self::INVALID_CREDENTIALS, $operation, $message, $diagnosticCode, $previous);
    }

    public static function unavailable(string $message, string $operation = 'connect', ?string $diagnosticCode = null, ?\Throwable $previous = null): self
    {
        return new self(self::UNAVAILABLE, $operation, $message, $diagnosticCode, $previous);
    }

    public static function searchFailed(string $message, ?string $diagnosticCode = null, ?\Throwable $previous = null): self
    {
        return new self(self::SEARCH_FAILED, 'search', $message, $diagnosticCode, $previous);
    }

    public static function identityNotFound(string $message): self
    {
        return new self(self::IDENTITY_NOT_FOUND, 'search', $message);
    }

    public static function identityNotUnique(string $message): self
    {
        return new self(self::IDENTITY_NOT_UNIQUE, 'search', $message);
    }

    public static function userMappingFailed(string $message): self
    {
        return new self(self::USER_MAPPING_FAILED, 'map_user', $message);
    }
}

<?php

namespace App\Services\Auth;

use App\Enums\AuditLogResult;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Ldap\LdapAuthenticationException;
use App\Services\Ldap\LdapAuthenticator;
use App\Services\Ldap\LdapIdentity;
use App\Services\Ldap\LdapSettings;
use App\Services\Ldap\LdapUserResolver;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;

/**
 * Shared staff credential-verification boundary used by both the web and the API login.
 *
 * When LDAP is enabled, the primary credential of an LDAP-managed (enrolled) staff account is
 * verified by exactly one directory bind and mapped to the pre-provisioned local User. Everything
 * after that - blocked/eligibility checks, login-type rules, TOTP, sessions, Sanctum tokens,
 * active role and login audit - stays in the existing transport-specific flows.
 *
 * The provider decision is server-side: it depends only on LDAP_ENABLED, the login type and
 * whether the account is enrolled. An LDAP failure never falls back to the local password.
 */
class StaffCredentialAuthenticator
{
    public const STAFF_LOGIN_TYPE = 'darbuotojas';

    public function __construct(
        private readonly LdapSettings $settings,
        private readonly LdapAuthenticator $ldap,
        private readonly LdapUserResolver $resolver,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function isLdapEnabled(): bool
    {
        return $this->settings->enabled;
    }

    /**
     * Whether a staff login may be submitted with a directory account name instead of an email.
     */
    public function acceptsDirectoryUsername(?string $loginType): bool
    {
        return $this->isLdapEnabled() && $loginType === self::STAFF_LOGIN_TYPE;
    }

    /**
     * Whether this attempt must be verified against LDAP. Decided before any directory contact:
     *  - LDAP disabled or a reporter login: never (existing local behaviour);
     *  - identifier without '@': a directory account name, always LDAP;
     *  - identifier with '@': LDAP only when that local account is LDAP-enrolled; a local-only
     *    staff account outside the rollout keeps its local password.
     */
    public function appliesTo(?string $loginType, string $identifier): bool
    {
        if (! $this->acceptsDirectoryUsername($loginType)) {
            return false;
        }

        if (! self::looksLikeEmail($identifier)) {
            return true;
        }

        $user = $this->findByEmail($identifier);

        return $user !== null && $user->hasLdapIdentity();
    }

    /**
     * Verifies the credential with LDAP and returns the pre-provisioned local user.
     * Failures are logged with a normalized reason and rethrown; only approval_pending has a public message.
     *
     * @throws LdapAuthenticationException
     */
    public function authenticate(string $identifier, #[SensitiveParameter] string $password): User
    {
        $identifier = trim($identifier);
        $expectedUser = null;
        $username = $identifier;

        if (self::looksLikeEmail($identifier)) {
            $expectedUser = $this->findByEmail($identifier);
            if ($expectedUser === null || ! $expectedUser->hasLdapIdentity()) {
                $this->fail($identifier, LdapAuthenticationException::userMappingFailed('Email identifier is not an LDAP-enrolled account.'));
            }
            $username = (string) $expectedUser->ldap_username;
        }

        if ($password === '') {
            $this->fail($username, LdapAuthenticationException::invalidCredentials('Empty password rejected before bind.', 'validate'));
        }

        try {
            $identity = $this->ldap->authenticate($username, $password);
            $user = $this->resolver->findLinked($identity);
            if ($user === null) {
                if ($expectedUser !== null) {
                    throw LdapAuthenticationException::userMappingFailed('Email identifier belongs to a different directory source.');
                }
                app(\App\Services\Ldap\LdapAccessService::class)->observe($identity, $this->settings->source);
                throw LdapAuthenticationException::approvalPending();
            }
        } catch (LdapAuthenticationException $e) {
            $this->fail($username, $e);
        }

        if ($expectedUser !== null && (int) $expectedUser->getKey() !== (int) $user->getKey()) {
            $this->fail($username, LdapAuthenticationException::userMappingFailed('Directory identity resolved to a different user than the email identifier.'));
        }

        Log::info('LDAP staff authentication succeeded', [
            'username' => $identity->normalizedUsername(),
            'bind_identity' => $this->settings->bindIdentity($username),
            'uri' => $this->settings->safeUri(),
            'user_id' => $user->getKey(),
        ]);

        return $user;
    }

    public static function looksLikeEmail(string $identifier): bool
    {
        return str_contains($identifier, '@');
    }

    private function findByEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->first();
    }

    /**
     * Logs a sanitized failure and rethrows. Never includes the password.
     */
    private function fail(string $username, LdapAuthenticationException $e): never
    {
        $context = [
            'reason' => $e->reason,
            'operation' => $e->operation,
            'username' => LdapIdentity::normalizeUsername($username),
            'bind_identity' => $this->settings->bindIdentity($username),
            'uri' => $this->settings->safeUri(),
            'ad_data_code' => $e->diagnosticCode,
            'detail' => $e->getMessage(),
        ];

        if ($e->reason === LdapAuthenticationException::UNAVAILABLE) {
            Log::error('LDAP staff authentication unavailable', $context);
        } else {
            Log::warning('LDAP staff authentication failed', $context);
        }

        if ($e->reason === LdapAuthenticationException::USER_MAPPING_FAILED) {
            // Auditable mapping reason (ADR-0001): a verified-but-unmapped or ambiguous identity.
            $this->auditLogService->logSystem('auth.ldap.user_mapping_failed', AuditLogResult::Failure, $e->getMessage(), [
                'username' => $context['username'],
                'source' => $this->settings->source,
            ]);
        }

        throw $e;
    }
}

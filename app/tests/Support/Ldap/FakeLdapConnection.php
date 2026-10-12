<?php

namespace Tests\Support\Ldap;

use App\Services\Ldap\LdapAuthenticationException;
use App\Services\Ldap\LdapConnection;
use SensitiveParameter;

/**
 * In-memory directory double. Records operations (never passwords) so tests can assert on
 * bind counts, filters and attribute lists without any network access.
 */
final class FakeLdapConnection implements LdapConnection
{
    /** @var array<string, string> bind identity => accepted password */
    public array $accounts = [];

    /** @var list<array{dn: string, attributes: array<string, list<string>>}> */
    public array $entries = [];

    /** @var array<string, list<string>> */
    public array $rootDse = [];

    public ?LdapAuthenticationException $bindFailure = null;

    public ?LdapAuthenticationException $searchFailure = null;

    public ?LdapAuthenticationException $rootDseFailure = null;

    /** @var list<string> bind identities only */
    public array $bindCalls = [];

    public int $anonymousBinds = 0;

    /** @var list<array{base_dn: string, filter: string, attributes: list<string>}> */
    public array $searchCalls = [];

    public int $closeCalls = 0;

    public function bind(string $identity, #[SensitiveParameter] string $password): void
    {
        $this->bindCalls[] = $identity;

        if ($this->bindFailure !== null) {
            throw $this->bindFailure;
        }

        if ($identity === '' || $password === '') {
            throw LdapAuthenticationException::invalidCredentials('Fake directory refuses empty bind.');
        }

        if (! array_key_exists($identity, $this->accounts) || ! hash_equals($this->accounts[$identity], $password)) {
            throw LdapAuthenticationException::invalidCredentials(
                sprintf('LDAP bind as "%s" failed: [49] Invalid credentials (AD data 52e)', $identity),
                'bind',
                '52e',
            );
        }
    }

    public function bindAnonymously(): void
    {
        $this->anonymousBinds++;

        if ($this->bindFailure !== null) {
            throw $this->bindFailure;
        }
    }

    public function search(string $baseDn, string $filter, array $attributes): array
    {
        $this->searchCalls[] = ['base_dn' => $baseDn, 'filter' => $filter, 'attributes' => $attributes];

        if ($this->searchFailure !== null) {
            throw $this->searchFailure;
        }

        return $this->entries;
    }

    public function readRootDse(array $attributes): array
    {
        if ($this->rootDseFailure !== null) {
            throw $this->rootDseFailure;
        }

        return $this->rootDse;
    }

    public function close(): void
    {
        $this->closeCalls++;
    }

    /**
     * @return array{dn: string, attributes: array<string, list<string>>}
     */
    public static function entry(string $username, ?string $displayName = 'Jonas Jonaitis', ?string $mail = 'jonas@example.com', ?string $dn = null): array
    {
        $dn ??= sprintf('CN=%s,CN=Users,DC=test,DC=invalid', $username);
        $attributes = ['samaccountname' => [$username], 'distinguishedname' => [$dn]];
        if ($displayName !== null) {
            $attributes['displayname'] = [$displayName];
        }
        if ($mail !== null) {
            $attributes['mail'] = [$mail];
        }

        return ['dn' => $dn, 'attributes' => $attributes];
    }
}

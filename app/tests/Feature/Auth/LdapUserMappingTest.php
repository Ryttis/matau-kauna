<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserRole;
use App\Services\Auth\StaffCredentialAuthenticator;
use App\Services\Ldap\LdapAuthenticationException;
use App\Services\Ldap\LdapIdentity;
use App\Services\Ldap\LdapUserResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\Support\Ldap\InteractsWithFakeLdap;
use Tests\TestCase;

class LdapUserMappingTest extends TestCase
{
    use InteractsWithFakeLdap;
    use RefreshDatabase;

    private const PASSWORD = 'Directory-Pass-1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableLdap();
        $this->setGlobalTwoFactorRequired(false);
    }

    private function identity(string $username): LdapIdentity
    {
        return new LdapIdentity($username, 'CN='.$username.',CN=Users,DC=test,DC=invalid', 'Jonas', $username.'@directory.example');
    }

    private function expectReason(string $reason, callable $callback): LdapAuthenticationException
    {
        try {
            $callback();
        } catch (LdapAuthenticationException $e) {
            $this->assertSame($reason, $e->reason);

            return $e;
        }

        $this->fail('Expected LdapAuthenticationException '.$reason);
    }

    public function test_valid_identity_resolves_the_explicitly_linked_user_case_insensitively(): void
    {
        $user = $this->ldapStaffUser('jdoe', ['email' => 'unrelated-address@example.com']);
        User::factory()->create(['email' => 'jdoe@directory.example']); // email match must be ignored

        $resolved = app(LdapUserResolver::class)->resolve($this->identity('JDoe'));

        $this->assertTrue($resolved->is($user));
    }

    public function test_unknown_identity_cannot_be_mapped_and_no_user_is_created(): void
    {
        User::factory()->create(['email' => 'ghost@directory.example']);
        $before = User::query()->count();

        $this->expectReason(LdapAuthenticationException::USER_MAPPING_FAILED, fn () => app(LdapUserResolver::class)->resolve($this->identity('ghost')));

        $this->assertSame($before, User::query()->count());
    }

    public function test_identity_from_another_directory_source_is_not_mapped(): void
    {
        $this->ldapStaffUser('jdoe', ['ldap_source' => 'other-directory']);

        $this->expectReason(LdapAuthenticationException::USER_MAPPING_FAILED, fn () => app(LdapUserResolver::class)->resolve($this->identity('jdoe')));
    }

    public function test_ambiguous_binding_fails_safely(): void
    {
        $this->ldapStaffUser('jdoe', ['email' => 'a@example.com']);
        $this->ldapStaffUser('JDoe', ['email' => 'b@example.com']);

        $this->expectReason(LdapAuthenticationException::USER_MAPPING_FAILED, fn () => app(LdapUserResolver::class)->resolve($this->identity('jdoe')));
    }

    public function test_email_identifier_of_enrolled_user_binds_with_the_linked_directory_account(): void
    {
        $user = $this->ldapStaffUser('jdoe', ['email' => 'jonas.jonaitis@kaunas.lt']);
        $this->directoryAccount('jdoe', self::PASSWORD);

        $resolved = app(StaffCredentialAuthenticator::class)->authenticate('jonas.jonaitis@kaunas.lt', self::PASSWORD);

        $this->assertTrue($resolved->is($user));
        $this->assertSame(['TEST\\jdoe'], $this->fakeLdap()->bindCalls);
    }

    public function test_email_identifier_enrolled_in_another_directory_source_is_rejected(): void
    {
        $this->ldapStaffUser('jdoe', ['email' => 'owner@example.com', 'ldap_source' => 'other-directory']);
        $this->directoryAccount('jdoe', self::PASSWORD);

        $this->expectReason(LdapAuthenticationException::USER_MAPPING_FAILED, fn () => app(StaffCredentialAuthenticator::class)->authenticate('owner@example.com', self::PASSWORD));
    }

    public function test_applies_to_decides_the_provider_server_side(): void
    {
        $authenticator = app(StaffCredentialAuthenticator::class);
        $this->ldapStaffUser('jdoe', ['email' => 'enrolled@example.com']);
        User::factory()->create(['email' => 'local-staff@example.com'])->roles()->sync([$this->role(UserRole::ROLE_EMPLOYEE)->id]);

        $this->assertTrue($authenticator->appliesTo('darbuotojas', 'jdoe'));
        $this->assertTrue($authenticator->appliesTo('darbuotojas', 'enrolled@example.com'));
        $this->assertFalse($authenticator->appliesTo('darbuotojas', 'local-staff@example.com'));
        $this->assertFalse($authenticator->appliesTo('darbuotojas', 'nobody@example.com'));
        $this->assertFalse($authenticator->appliesTo('pareiskejas', 'jdoe'));
        $this->assertFalse($authenticator->appliesTo('pareiskejas', 'enrolled@example.com'));

        config(['ldap.enabled' => false]);
        $this->assertFalse(app(StaffCredentialAuthenticator::class)->appliesTo('darbuotojas', 'jdoe'));
        $this->assertFalse(app(StaffCredentialAuthenticator::class)->appliesTo('darbuotojas', 'enrolled@example.com'));
    }

    public function test_reporter_only_account_with_a_binding_does_not_gain_staff_access(): void
    {
        $reporter = $this->ldapStaffUser('citizen', roles: [UserRole::ROLE_REPORTER]);
        $this->directoryAccount('citizen', self::PASSWORD);

        $this->post('/login', ['email' => 'citizen', 'password' => self::PASSWORD, 'login_type' => 'darbuotojas'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame([UserRole::ROLE_REPORTER], $reporter->fresh()->roles->pluck('role')->all());

        // Reporter login never uses LDAP: the local password still applies and nothing was bound.
        $this->fakeLdap()->bindCalls = [];
        $this->post('/login', ['email' => $reporter->email, 'password' => 'password', 'login_type' => 'pareiskejas'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($reporter);
        $this->assertSame([], $this->fakeLdap()->bindCalls);
    }

    public function test_blocked_enrolled_user_remains_blocked_after_directory_success(): void
    {
        $user = $this->ldapStaffUser('blocked', ['blocked_at' => now()]);
        $this->directoryAccount('blocked', self::PASSWORD);

        $this->post('/login', ['email' => 'blocked', 'password' => self::PASSWORD, 'login_type' => 'darbuotojas'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->postJson('/api/login', ['email' => 'blocked', 'password' => self::PASSWORD, 'login_type' => 'darbuotojas'])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_enrolled_user_local_password_is_not_consulted_while_ldap_is_enabled(): void
    {
        $user = $this->ldapStaffUser('jdoe', ['password' => Hash::make('Local-Password-1')]);
        $this->directoryAccount('jdoe', self::PASSWORD);

        foreach (['jdoe', $user->email] as $identifier) {
            $this->post('/login', ['email' => $identifier, 'password' => 'Local-Password-1', 'login_type' => 'darbuotojas'])
                ->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_mapping_failure_is_logged_without_the_password(): void
    {
        Log::spy();
        $this->directoryAccount('unmapped', self::PASSWORD);

        $this->post('/login', ['email' => 'unmapped', 'password' => self::PASSWORD, 'login_type' => 'darbuotojas'])
            ->assertSessionHasErrors(['email' => __('auth.failed', [], 'lt')]);

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === 'LDAP staff authentication failed'
                && $context['reason'] === LdapAuthenticationException::USER_MAPPING_FAILED
                && $context['username'] === 'unmapped'
                && $context['bind_identity'] === 'TEST\\unmapped'
                && ! str_contains(json_encode($context), self::PASSWORD);
        })->once();
    }
}

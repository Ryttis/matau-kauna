<?php

namespace Tests\Feature\Auth;

use App\Models\LoginLog;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Ldap\LdapAuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Ldap\InteractsWithFakeLdap;
use Tests\TestCase;

class LdapWebLoginTest extends TestCase
{
    use InteractsWithFakeLdap;
    use RefreshDatabase;

    private const LDAP_PASSWORD = 'Directory-Pass-1';

    private const LOCAL_PASSWORD = 'Local-Pass-1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setGlobalTwoFactorRequired(false);
    }

    private function staffLogin(string $identifier, string $password, bool $remember = false)
    {
        return $this->post('/login', [
            'email' => $identifier,
            'password' => $password,
            'login_type' => 'darbuotojas',
            'remember' => $remember,
        ]);
    }

    public function test_ldap_disabled_preserves_local_password_login_even_for_enrolled_users(): void
    {
        $this->enableLdap(false);
        $user = $this->ldapStaffUser('jdoe', ['password' => Hash::make(self::LOCAL_PASSWORD)]);

        $this->staffLogin($user->email, self::LOCAL_PASSWORD)->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame([], $this->fakeLdap()->bindCalls);
        $this->assertSame(0, $this->fakeLdapFactory->created);
    }

    public function test_ldap_disabled_still_requires_an_email_identifier(): void
    {
        $this->enableLdap(false);
        $this->ldapStaffUser('jdoe');

        $this->staffLogin('jdoe', self::LOCAL_PASSWORD)->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, $this->fakeLdapFactory->created);
    }

    public function test_login_page_exposes_username_login_only_when_ldap_is_enabled(): void
    {
        $this->enableLdap(false);
        $this->get('/login?role=darbuotojas')->assertInertia(fn (Assert $page) => $page->where('staffUsernameLogin', false));

        $this->enableLdap();
        $this->get('/login?role=darbuotojas')->assertInertia(fn (Assert $page) => $page->where('staffUsernameLogin', true));
    }

    public function test_ldap_enabled_authenticates_enrolled_staff_with_the_directory_password(): void
    {
        $this->enableLdap();
        $user = $this->ldapStaffUser('jdoe', ['password' => Hash::make(self::LOCAL_PASSWORD)]);
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        $this->staffLogin('JDoe', self::LDAP_PASSWORD)->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(['TEST\\jdoe'], $this->fakeLdap()->bindCalls);
        $this->assertDatabaseHas('login_logs', ['user_id' => $user->id, 'event_type' => LoginLog::EVENT_LOGIN]);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'auth.login']);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_ldap_enabled_remember_me_still_works(): void
    {
        $this->enableLdap();
        $user = $this->ldapStaffUser('jdoe');
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        $this->staffLogin('jdoe', self::LDAP_PASSWORD, remember: true)->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty($user->fresh()->remember_token);
    }

    public function test_ldap_enabled_keeps_local_password_for_staff_outside_the_rollout(): void
    {
        $this->enableLdap();
        $local = User::factory()->create(['email' => 'local-staff@example.com', 'password' => Hash::make(self::LOCAL_PASSWORD)]);
        $local->roles()->sync([$this->role(UserRole::ROLE_EMPLOYEE)->id]);

        $this->staffLogin('local-staff@example.com', self::LOCAL_PASSWORD)->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($local);
        $this->assertSame(0, $this->fakeLdapFactory->created);
    }

    public function test_successful_ldap_authentication_still_requires_totp(): void
    {
        $this->enableLdap();
        $user = $this->ldapStaffUser('jdoe', [
            'two_factor_enabled' => true,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        $this->staffLogin('jdoe', self::LDAP_PASSWORD)
            ->assertRedirect(route('two-factor.challenge'))
            ->assertSessionHas('two_factor_pending_user_id', $user->id);

        $this->assertGuest();
        $this->assertDatabaseMissing('login_logs', ['user_id' => $user->id]);

        $this->post(route('two-factor.challenge.store'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_invalid_ldap_credentials_return_the_generic_login_failure(): void
    {
        $this->enableLdap();
        $user = $this->ldapStaffUser('jdoe');
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        $this->staffLogin('jdoe', 'wrong-password')
            ->assertSessionHasErrors(['email' => __('auth.failed', [], 'lt')]);

        $this->assertGuest();
        $this->assertDatabaseMissing('login_logs', ['user_id' => $user->id]);
    }

    public function test_ldap_outage_does_not_fall_back_to_the_local_password(): void
    {
        $this->enableLdap();
        $user = $this->ldapStaffUser('jdoe', ['password' => Hash::make(self::LOCAL_PASSWORD)]);
        $this->directoryAccount('jdoe', self::LOCAL_PASSWORD);
        $this->fakeLdap()->bindFailure = LdapAuthenticationException::unavailable("Can't contact LDAP server", 'connect');
        Log::spy();

        foreach (['jdoe', $user->email] as $identifier) {
            $this->staffLogin($identifier, self::LOCAL_PASSWORD)
                ->assertSessionHasErrors(['email' => __('auth.failed', [], 'lt')]);
            $this->assertGuest();
        }

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context): bool {
            return $message === 'LDAP staff authentication unavailable'
                && $context['reason'] === LdapAuthenticationException::UNAVAILABLE
                && ! str_contains(json_encode($context), self::LOCAL_PASSWORD);
        })->twice();
    }

    public function test_empty_password_never_reaches_the_directory(): void
    {
        $this->enableLdap();
        $this->ldapStaffUser('jdoe');
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        $this->staffLogin('jdoe', '')->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertSame([], $this->fakeLdap()->bindCalls);
    }

    public function test_ldap_login_restores_saved_active_role_and_session(): void
    {
        $this->enableLdap();
        $employee = $this->role(UserRole::ROLE_EMPLOYEE);
        $admin = $this->role(UserRole::ROLE_SYSTEM_ADMIN);
        $user = $this->ldapStaffUser('jdoe', ['active_role_id' => $admin->id], roles: [UserRole::ROLE_EMPLOYEE, UserRole::ROLE_SYSTEM_ADMIN]);
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        $this->staffLogin('jdoe', self::LDAP_PASSWORD)
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('active_role_id', (string) $admin->id);

        $this->assertAuthenticatedAs($user);
        $this->assertSame([$employee->role, $admin->role], $user->fresh()->roles->pluck('role')->sort()->values()->all());
    }

    public function test_ldap_login_is_rate_limited_like_local_login(): void
    {
        $this->enableLdap();
        $this->ldapStaffUser('jdoe');
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        for ($i = 0; $i < 5; $i++) {
            $this->staffLogin('jdoe', 'wrong')->assertSessionHasErrors('email');
        }
        $bindsBefore = count($this->fakeLdap()->bindCalls);

        $this->staffLogin('jdoe', self::LDAP_PASSWORD)->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame($bindsBefore, count($this->fakeLdap()->bindCalls));
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserRole;
use App\Services\Ldap\LdapAuthenticationException;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use Tests\Support\Ldap\InteractsWithFakeLdap;
use Tests\TestCase;

class LdapApiLoginTest extends TestCase
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

    private function apiStaffLogin(string $identifier, string $password, array $extra = [])
    {
        return $this->postJson('/api/login', array_merge([
            'email' => $identifier,
            'password' => $password,
            'login_type' => 'darbuotojas',
            'device_name' => 'mobile-ios',
        ], $extra));
    }

    private function assertNoToken(User $user): void
    {
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id, 'tokenable_type' => User::class]);
    }

    public function test_ldap_disabled_preserves_local_password_login(): void
    {
        $this->enableLdap(false);
        $user = $this->ldapStaffUser('jdoe', ['password' => Hash::make(self::LOCAL_PASSWORD)]);

        $response = $this->apiStaffLogin($user->email, self::LOCAL_PASSWORD)->assertOk();

        $this->assertSame($user->id, PersonalAccessToken::findToken($response->json('token'))?->tokenable_id);
        $this->assertSame(0, $this->fakeLdapFactory->created);

        $this->apiStaffLogin('jdoe', self::LOCAL_PASSWORD)->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_ldap_enabled_issues_a_token_after_directory_authentication(): void
    {
        $this->enableLdap();
        $user = $this->ldapStaffUser('jdoe', ['password' => Hash::make(self::LOCAL_PASSWORD)]);
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        $response = $this->apiStaffLogin('jdoe', self::LDAP_PASSWORD)->assertOk();

        $response->assertJsonPath('user.id', $user->id);
        $this->assertSame($user->id, PersonalAccessToken::findToken($response->json('token'))?->tokenable_id);
        $this->assertSame(['TEST\\jdoe'], $this->fakeLdap()->bindCalls);

        // The local password no longer authenticates an enrolled account.
        $this->apiStaffLogin('jdoe', self::LOCAL_PASSWORD)->assertUnprocessable();
        $this->apiStaffLogin($user->email, self::LOCAL_PASSWORD)->assertUnprocessable();
    }

    public function test_ldap_enabled_accepts_email_identifier_of_enrolled_user(): void
    {
        $this->enableLdap();
        $user = $this->ldapStaffUser('jdoe', ['email' => 'jonas@kaunas.lt']);
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        $response = $this->apiStaffLogin('jonas@kaunas.lt', self::LDAP_PASSWORD)->assertOk();

        $this->assertSame($user->id, PersonalAccessToken::findToken($response->json('token'))?->tokenable_id);
        $this->assertSame(['TEST\\jdoe'], $this->fakeLdap()->bindCalls);
    }

    public function test_ldap_enabled_keeps_local_password_for_staff_outside_the_rollout(): void
    {
        $this->enableLdap();
        $local = User::factory()->create(['email' => 'local-staff@example.com', 'password' => Hash::make(self::LOCAL_PASSWORD)]);
        $local->roles()->sync([$this->role(UserRole::ROLE_EMPLOYEE)->id]);

        $this->apiStaffLogin('local-staff@example.com', self::LOCAL_PASSWORD)->assertOk();
        $this->assertSame(0, $this->fakeLdapFactory->created);
    }

    public function test_reporter_login_type_never_uses_ldap(): void
    {
        $this->enableLdap();
        $reporter = User::factory()->create(['email' => 'citizen@example.com', 'password' => Hash::make(self::LOCAL_PASSWORD)]);
        $reporter->roles()->sync([$this->role(UserRole::ROLE_REPORTER)->id]);

        $this->postJson('/api/login', ['email' => 'citizen@example.com', 'password' => self::LOCAL_PASSWORD, 'login_type' => 'pareiskejas'])->assertOk();
        $this->postJson('/api/login', ['email' => 'citizen', 'password' => self::LOCAL_PASSWORD, 'login_type' => 'pareiskejas'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame(0, $this->fakeLdapFactory->created);
    }

    public function test_successful_ldap_authentication_does_not_bypass_api_totp(): void
    {
        $this->enableLdap();
        $user = $this->ldapStaffUser('jdoe', [
            'two_factor_enabled' => true,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        $login = $this->apiStaffLogin('jdoe', self::LDAP_PASSWORD)->assertOk();

        $login->assertJsonPath('two_factor_required', true)->assertJsonMissingPath('token');
        $this->assertNoToken($user);

        $this->postJson('/api/two-factor/challenge', ['login_token' => $login->json('login_token'), 'code' => '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertNoToken($user);
    }

    public function test_api_totp_challenge_completes_ldap_login(): void
    {
        $this->enableLdap();
        $user = $this->ldapStaffUser('jdoe', [
            'two_factor_enabled' => true,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        $this->mock(TwoFactorService::class, function ($mock): void {
            $mock->shouldReceive('ensurePrepared')->once()->with(Mockery::type(User::class));
            $mock->shouldReceive('requiresChallenge')->twice()->with(Mockery::type(User::class))->andReturn(true);
            $mock->shouldReceive('verifyCode')->once()->with(Mockery::type(User::class), '123456')->andReturn(true);
        });

        $login = $this->apiStaffLogin('jdoe', self::LDAP_PASSWORD)->assertOk();
        $response = $this->postJson('/api/two-factor/challenge', ['login_token' => $login->json('login_token'), 'code' => '123456'])->assertOk();

        $this->assertSame($user->id, PersonalAccessToken::findToken($response->json('token'))?->tokenable_id);
        $this->assertDatabaseHas('login_logs', ['user_id' => $user->id]);
    }

    public function test_invalid_ldap_credentials_return_generic_failure(): void
    {
        $this->enableLdap();
        $user = $this->ldapStaffUser('jdoe');
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        $this->apiStaffLogin('jdoe', 'wrong-password')
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('auth.failed', [], 'lt'));
        $this->assertNoToken($user);
    }

    public function test_ldap_outage_does_not_fall_back_to_the_local_password(): void
    {
        $this->enableLdap();
        $user = $this->ldapStaffUser('jdoe', ['password' => Hash::make(self::LOCAL_PASSWORD)]);
        $this->fakeLdap()->bindFailure = LdapAuthenticationException::unavailable("Can't contact LDAP server", 'connect');

        foreach (['jdoe', $user->email] as $identifier) {
            $this->apiStaffLogin($identifier, self::LOCAL_PASSWORD)
                ->assertUnprocessable()
                ->assertJsonPath('errors.email.0', __('auth.failed', [], 'lt'));
        }
        $this->assertNoToken($user);
    }

    public function test_empty_password_never_reaches_the_directory(): void
    {
        $this->enableLdap();
        $this->ldapStaffUser('jdoe');
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        $this->apiStaffLogin('jdoe', '')->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame([], $this->fakeLdap()->bindCalls);
    }

    public function test_remembered_session_and_active_role_behaviour_are_preserved(): void
    {
        $this->enableLdap();
        $admin = $this->role(UserRole::ROLE_SYSTEM_ADMIN);
        $user = $this->ldapStaffUser('jdoe', ['active_role_id' => $admin->id], roles: [UserRole::ROLE_EMPLOYEE, UserRole::ROLE_SYSTEM_ADMIN]);
        $this->directoryAccount('jdoe', self::LDAP_PASSWORD);

        $response = $this->apiStaffLogin('jdoe', self::LDAP_PASSWORD, ['remember_session' => true])->assertOk();
        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertContains('remember-session', $token?->abilities ?? []);

        $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('active_role_id', (string) $admin->id)
            ->assertJsonPath('id', $user->id);
    }
}

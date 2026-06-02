<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordPolicy;
use App\Models\User;
use App\Models\UserRole;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('initialLoginType', 'pareiskejas')
        );
    }

    public function test_login_screen_can_default_to_worker_tab(): void
    {
        $response = $this->get('/login?role=darbuotojas');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('initialLoginType', 'darbuotojas')
        );
    }

    public function test_login_screen_ignores_invalid_role_query(): void
    {
        $response = $this->get('/login?role=admin');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('initialLoginType', 'pareiskejas')
        );
    }

    public function test_verified_user_visiting_login_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_unverified_user_can_open_login_while_authenticated(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();
        $user->roles()->sync([$this->reporterRole()->id]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'login_type' => 'pareiskejas',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'login_type' => 'pareiskejas',
        ]);

        $this->assertGuest();
    }

    public function test_viisp_user_without_local_password_cannot_use_email_login(): void
    {
        $reporterRole = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_REPORTER],
            ['name' => 'Pareiškėjas']
        );

        $user = User::factory()->create([
            'viisp_personal_code_hash' => bin2hex(random_bytes(16)),
            'viisp_local_password_set_at' => null,
            'password' => Hash::make('password'),
        ]);
        $user->roles()->sync([$reporterRole->id]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'login_type' => 'pareiskejas',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_profile_active_role_switch_is_persisted_for_next_login(): void
    {
        [$employeeRole, $systemAdminRole] = $this->staffRolesForActiveRoleTests();
        $user = User::factory()->create();
        $user->roles()->sync([$employeeRole->id, $systemAdminRole->id]);

        $this
            ->actingAs($user)
            ->patch(route('settings.active-role'), [
                'role_id' => $systemAdminRole->id,
            ])
            ->assertRedirect();

        $this->assertSame((string) $systemAdminRole->id, (string) $user->refresh()->active_role_id);
    }

    public function test_login_restores_saved_active_role(): void
    {
        [$employeeRole, $systemAdminRole] = $this->staffRolesForActiveRoleTests();
        $user = User::factory()->create([
            'email' => 'staff-active-role@example.com',
            'password' => Hash::make('password'),
            'active_role_id' => $systemAdminRole->id,
        ]);
        $user->roles()->sync([$employeeRole->id, $systemAdminRole->id]);
        $this->setGlobalTwoFactorRequired(false);

        $this
            ->post('/login', [
                'email' => 'staff-active-role@example.com',
                'password' => 'password',
                'login_type' => 'darbuotojas',
            ])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('active_role_id', (string) $systemAdminRole->id);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_falls_back_when_saved_active_role_is_no_longer_assigned(): void
    {
        [$employeeRole, $systemAdminRole] = $this->staffRolesForActiveRoleTests();
        $user = User::factory()->create([
            'email' => 'staff-active-role-fallback@example.com',
            'password' => Hash::make('password'),
            'active_role_id' => $systemAdminRole->id,
        ]);
        $user->roles()->sync([$employeeRole->id]);
        $this->setGlobalTwoFactorRequired(false);

        $this
            ->post('/login', [
                'email' => 'staff-active-role-fallback@example.com',
                'password' => 'password',
                'login_type' => 'darbuotojas',
            ])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('active_role_id', (string) $employeeRole->id);

        $this->assertAuthenticatedAs($user);
    }

    public function test_two_factor_login_restores_saved_active_role(): void
    {
        [$employeeRole, $systemAdminRole] = $this->staffRolesForActiveRoleTests();
        $user = User::factory()->create([
            'active_role_id' => $systemAdminRole->id,
            'two_factor_enabled' => true,
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
        $user->roles()->sync([$employeeRole->id, $systemAdminRole->id]);

        $this->mock(TwoFactorService::class, function ($mock): void {
            $mock->shouldReceive('requiresChallenge')->andReturn(true);
            $mock
                ->shouldReceive('verifyCode')
                ->once()
                ->with(Mockery::type(User::class), '123456')
                ->andReturn(true);
        });

        $this
            ->withSession(['two_factor_pending_user_id' => $user->id])
            ->post(route('two-factor.challenge.store'), [
                'code' => '123456',
            ])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('active_role_id', (string) $systemAdminRole->id);

        $this->assertAuthenticatedAs($user);
    }

    public function test_viisp_login_restores_saved_active_role(): void
    {
        [$employeeRole, $systemAdminRole] = $this->staffRolesForActiveRoleTests();
        $personalCodeHash = bin2hex(random_bytes(16));
        $user = User::factory()->create([
            'email' => 'viisp-staff@example.com',
            'name' => 'Viisp',
            'surname' => 'Staff',
            'viisp_personal_code_hash' => $personalCodeHash,
            'active_role_id' => $systemAdminRole->id,
        ]);
        $user->roles()->sync([$employeeRole->id, $systemAdminRole->id]);

        config([
            'services.viisp.exchange_secret' => 'test-secret',
            'services.viisp.gateway_url' => 'https://viisp.test',
        ]);
        Http::fake([
            'https://viisp.test/gateway/viisp/exchange/test-token' => Http::response([
                'identity' => [
                    'hashedCode' => $personalCodeHash,
                    'firstName' => 'Viisp',
                    'lastName' => 'Staff',
                    'email' => 'viisp-staff@example.com',
                ],
            ]),
        ]);

        $this
            ->get(route('viisp.callback', ['token' => 'test-token']))
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('active_role_id', (string) $systemAdminRole->id);

        $this->assertAuthenticatedAs($user);
    }

    private function staffRolesForActiveRoleTests(): array
    {
        return [
            UserRole::firstOrCreate(
                ['role' => UserRole::ROLE_EMPLOYEE],
                ['name' => 'Įstaigos darbuotojas'],
            ),
            UserRole::firstOrCreate(
                ['role' => UserRole::ROLE_SYSTEM_ADMIN],
                ['name' => 'Sistemos administratorius'],
            ),
        ];
    }

    private function reporterRole(): UserRole
    {
        return UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_REPORTER],
            ['name' => 'Reporter'],
        );
    }

    private function setGlobalTwoFactorRequired(bool $enabled): void
    {
        PasswordPolicy::query()->delete();
        PasswordPolicy::create([
            'min_length' => 8,
            'require_lowercase' => true,
            'require_uppercase' => true,
            'require_special' => true,
            'two_factor_can_enable' => $enabled,
            'prohibit_name_email' => true,
        ]);
        PasswordPolicy::clearCache();
    }
}

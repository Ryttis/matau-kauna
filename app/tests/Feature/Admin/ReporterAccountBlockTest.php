<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use App\Services\UserBlockingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReporterAccountBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_block_reporter_and_revoke_access(): void
    {
        $admin = $this->createSystemAdmin();
        $reporter = $this->createReporter(['email' => 'blocked-reporter@example.com']);
        $token = $reporter->createToken('mobile');
        DB::table('sessions')->insert([
            'id' => 'reporter-session',
            'user_id' => $reporter->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson('/admin/rbac/users/'.$reporter->id.'/block')
            ->assertOk();

        $response
            ->assertJsonPath('id', $reporter->id)
            ->assertJsonPath('blocked_by', $admin->id);

        $reporter->refresh();
        $this->assertNotNull($reporter->blocked_at);
        $this->assertSame($admin->id, $reporter->blocked_by);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'reporter-session']);
    }

    public function test_admin_can_unblock_reporter_and_login_is_allowed_again(): void
    {
        $admin = $this->createSystemAdmin();
        $reporter = $this->createReporter([
            'email' => 'unblocked-reporter@example.com',
            'blocked_at' => now(),
            'blocked_by' => $admin->id,
        ]);

        $this
            ->actingAs($admin)
            ->postJson('/admin/rbac/users/'.$reporter->id.'/unblock')
            ->assertOk()
            ->assertJsonPath('id', $reporter->id)
            ->assertJsonPath('unblocked_by', $admin->id);

        $reporter->refresh();
        $this->assertNull($reporter->blocked_at);
        $this->assertNull($reporter->blocked_by);
        $this->assertNotNull($reporter->unblocked_at);
        $this->assertSame($admin->id, $reporter->unblocked_by);

        $this
            ->post('/login', [
                'email' => 'unblocked-reporter@example.com',
                'password' => 'password',
                'login_type' => 'pareiskejas',
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($reporter);
    }

    public function test_blocked_reporter_cannot_login_with_web_or_api_password(): void
    {
        $reporter = $this->createReporter([
            'email' => 'blocked-login@example.com',
            'blocked_at' => now(),
        ]);

        $this
            ->post('/login', [
                'email' => 'blocked-login@example.com',
                'password' => 'password',
                'login_type' => 'pareiskejas',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this
            ->postJson('/api/login', [
                'email' => 'blocked-login@example.com',
                'password' => 'password',
                'login_type' => 'pareiskejas',
                'device_name' => 'mobile',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', UserBlockingService::BLOCKED_MESSAGE);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $reporter->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_blocked_reporter_existing_api_token_is_rejected_and_deleted(): void
    {
        $reporter = $this->createReporter([
            'email' => 'blocked-token@example.com',
            'blocked_at' => now(),
        ]);
        $token = $reporter->createToken('mobile');

        $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'account_blocked');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_blocked_reporter_cannot_login_with_web_or_mobile_viisp(): void
    {
        $hash = bin2hex(random_bytes(16));
        $this->createReporter([
            'email' => 'blocked-viisp@example.com',
            'viisp_personal_code_hash' => $hash,
            'blocked_at' => now(),
        ]);

        config([
            'services.viisp.exchange_secret' => 'test-secret',
            'services.viisp.gateway_url' => 'https://viisp.test',
            'services.viisp.gateway_postback_base' => 'https://app.test/viisp-postback',
        ]);
        Http::fake([
            'https://viisp.test/gateway/viisp/login' => Http::response([
                'redirectUrl' => 'https://viisp.test/login/redirect',
            ]),
            'https://viisp.test/gateway/viisp/exchange/test-token' => Http::response([
                'identity' => [
                    'hashedCode' => $hash,
                    'firstName' => 'Blocked',
                    'lastName' => 'Reporter',
                    'email' => 'blocked-viisp@example.com',
                ],
            ]),
        ]);

        $this
            ->get(route('viisp.callback', ['token' => 'test-token']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $initResponse = $this->postJson('/api/viisp/init', [
            'redirect_uri' => 'kaunoproblemos://auth/viisp',
            'device_name' => 'mobile-ios',
        ])->assertOk();

        $this
            ->getJson('/api/viisp/callback?state='.$initResponse->json('state').'&token=test-token')
            ->assertForbidden()
            ->assertJsonPath('error', 'account_blocked');
    }

    public function test_admin_cannot_block_user_with_staff_role(): void
    {
        $admin = $this->createSystemAdmin();
        $user = $this->createReporter(['email' => 'staff-reporter@example.com']);
        $employee = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_EMPLOYEE],
            ['name' => 'Employee']
        );
        $user->roles()->syncWithoutDetaching([$employee->id]);

        $this
            ->actingAs($admin)
            ->postJson('/admin/rbac/users/'.$user->id.'/block')
            ->assertUnprocessable()
            ->assertJsonPath('message', UserBlockingService::NOT_REPORTER_ONLY_MESSAGE);

        $this->assertNull($user->refresh()->blocked_at);
    }

    public function test_users_endpoint_can_filter_blocked_accounts(): void
    {
        $admin = $this->createSystemAdmin();
        $active = $this->createReporter(['email' => 'active-reporter@example.com']);
        $blocked = $this->createReporter([
            'email' => 'blocked-filter@example.com',
            'blocked_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->getJson('/admin/rbac/users?blocked=1')
            ->assertOk()
            ->assertJsonFragment(['id' => $blocked->id])
            ->assertJsonMissing(['id' => $active->id]);

        $this
            ->actingAs($admin)
            ->getJson('/admin/rbac/users?blocked=0')
            ->assertOk()
            ->assertJsonFragment(['id' => $active->id])
            ->assertJsonMissing(['id' => $blocked->id]);
    }

    private function createSystemAdmin(): User
    {
        $permission = UserRolePermission::firstOrCreate(
            ['permission' => 'admin.access'],
            ['name' => 'Admin access']
        );
        $role = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_SYSTEM_ADMIN],
            ['name' => 'System administrator']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    private function createReporter(array $attributes = []): User
    {
        $role = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_REPORTER],
            ['name' => 'Reporter']
        );
        $user = User::factory()->create($attributes);
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }
}

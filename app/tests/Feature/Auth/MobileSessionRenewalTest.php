<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserRole;
use App\Services\TwoFactorService;
use App\Support\TokenExpiration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use Tests\TestCase;

class MobileSessionRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_api_login_uses_normal_session_lifetime_by_default(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 10:00:00'));
        config(['session.lifetime' => 120, 'kaunas.mobile_session_lifetime_days' => 90]);
        $user = $this->reporterUser('normal-mobile@example.com');

        $response = $this->postJson('/api/login', [
            'email' => 'normal-mobile@example.com',
            'password' => 'password',
            'login_type' => 'pareiskejas',
            'device_name' => 'mobile-ios',
        ])->assertOk();

        $response->assertJsonPath('expires_at', now()->addMinutes(120)->toIso8601String());
        $accessToken = PersonalAccessToken::findToken($response->json('token'));

        $this->assertSame($user->id, $accessToken?->tokenable_id);
        $this->assertSame(['*'], $accessToken?->abilities);
    }

    public function test_api_login_can_issue_remembered_mobile_session(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 10:00:00'));
        config(['session.lifetime' => 120, 'kaunas.mobile_session_lifetime_days' => 90]);
        $user = $this->reporterUser('remembered-mobile@example.com');

        $response = $this->postJson('/api/login', [
            'email' => 'remembered-mobile@example.com',
            'password' => 'password',
            'login_type' => 'pareiskejas',
            'device_name' => 'mobile-ios',
            'remember_session' => true,
        ])->assertOk();

        $response->assertJsonPath('expires_at', now()->addDays(90)->toIso8601String());
        $accessToken = PersonalAccessToken::findToken($response->json('token'));

        $this->assertSame($user->id, $accessToken?->tokenable_id);
        $this->assertContains(TokenExpiration::REMEMBER_SESSION_ABILITY, $accessToken?->abilities ?? []);
    }

    public function test_two_factor_login_preserves_remembered_session_request(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 10:00:00'));
        config(['session.lifetime' => 120, 'kaunas.mobile_session_lifetime_days' => 90]);
        $user = $this->reporterUser('two-factor-mobile@example.com', [
            'two_factor_enabled' => true,
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->mock(TwoFactorService::class, function ($mock): void {
            $mock->shouldReceive('ensurePrepared')->once()->with(Mockery::type(User::class));
            $mock->shouldReceive('requiresChallenge')->twice()->with(Mockery::type(User::class))->andReturn(true);
            $mock->shouldReceive('verifyCode')->once()->with(Mockery::type(User::class), '123456')->andReturn(true);
        });

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'two-factor-mobile@example.com',
            'password' => 'password',
            'login_type' => 'pareiskejas',
            'device_name' => 'mobile-ios',
            'remember_session' => true,
        ])->assertOk();

        $response = $this->postJson('/api/two-factor/challenge', [
            'login_token' => $loginResponse->json('login_token'),
            'code' => '123456',
        ])->assertOk();

        $response->assertJsonPath('expires_at', now()->addDays(90)->toIso8601String());
        $accessToken = PersonalAccessToken::findToken($response->json('token'));

        $this->assertSame($user->id, $accessToken?->tokenable_id);
        $this->assertContains(TokenExpiration::REMEMBER_SESSION_ABILITY, $accessToken?->abilities ?? []);
    }

    public function test_registration_can_issue_remembered_mobile_session(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 10:00:00'));
        config(['kaunas.mobile_session_lifetime_days' => 90]);

        $response = $this->postJson('/api/register', [
            'name' => 'Mobile Reporter',
            'email' => 'registered-mobile@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'device_name' => 'mobile-ios',
            'remember_session' => true,
        ])->assertCreated();

        $response->assertJsonPath('expires_at', now()->addDays(90)->toIso8601String());
        $accessToken = PersonalAccessToken::findToken($response->json('token'));

        $this->assertContains(TokenExpiration::REMEMBER_SESSION_ABILITY, $accessToken?->abilities ?? []);
    }

    public function test_viisp_callback_preserves_remembered_mobile_session(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 10:00:00'));
        config([
            'kaunas.mobile_session_lifetime_days' => 90,
            'services.viisp.exchange_secret' => 'test-secret',
            'services.viisp.gateway_url' => 'https://viisp.test',
            'services.viisp.gateway_postback_base' => 'https://app.test/viisp-postback',
        ]);
        $personalCodeHash = bin2hex(random_bytes(16));
        $user = $this->reporterUser('viisp-mobile@example.com', [
            'viisp_personal_code_hash' => $personalCodeHash,
        ]);

        Http::fake([
            'https://viisp.test/gateway/viisp/login' => Http::response([
                'redirectUrl' => 'https://viisp.test/login/redirect',
            ]),
            'https://viisp.test/gateway/viisp/exchange/test-token' => Http::response([
                'identity' => [
                    'hashedCode' => $personalCodeHash,
                    'firstName' => 'Mobile',
                    'lastName' => 'Reporter',
                    'email' => 'viisp-mobile@example.com',
                ],
            ]),
        ]);

        $initResponse = $this->postJson('/api/viisp/init', [
            'redirect_uri' => 'kaunoproblemos://auth/viisp',
            'device_name' => 'mobile-ios',
            'remember_session' => true,
        ])->assertOk();

        $response = $this->getJson('/api/viisp/callback?state='.$initResponse->json('state').'&token=test-token')
            ->assertOk();

        $response->assertJsonPath('expires_at', now()->addDays(90)->toIso8601String());
        $accessToken = PersonalAccessToken::findToken($response->json('token'));

        $this->assertSame($user->id, $accessToken?->tokenable_id);
        $this->assertContains(TokenExpiration::REMEMBER_SESSION_ABILITY, $accessToken?->abilities ?? []);
    }

    public function test_authenticated_request_renews_remembered_session_and_exposes_expiry(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 10:00:00'));
        config(['kaunas.mobile_session_lifetime_days' => 90]);
        $user = $this->reporterUser('renew-remembered@example.com');
        $token = $user->createToken(
            'mobile-ios',
            TokenExpiration::abilitiesForRememberSession(true),
            now()->addDay()
        );

        $expected = now()->addDays(90)->toIso8601String();

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/me')
            ->assertOk()
            ->assertHeader('X-Session-Expires-At', $expected)
            ->assertJsonPath('session_expires_at', $expected);

        $this->assertSame($expected, $token->accessToken->refresh()->expires_at?->toIso8601String());
    }

    public function test_authenticated_request_renews_normal_session_with_normal_lifetime(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 10:00:00'));
        config(['session.lifetime' => 120, 'kaunas.mobile_session_lifetime_days' => 90]);
        $user = $this->reporterUser('renew-normal@example.com');
        $token = $user->createToken('mobile-ios', ['*'], now()->addMinutes(30));

        $expected = now()->addMinutes(120)->toIso8601String();

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/me')
            ->assertOk()
            ->assertHeader('X-Session-Expires-At', $expected)
            ->assertJsonPath('session_expires_at', $expected);

        $this->assertSame($expected, $token->accessToken->refresh()->expires_at?->toIso8601String());
    }

    private function reporterUser(string $email, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['email' => $email], $attributes));
        $role = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_REPORTER],
            ['name' => 'Reporter']
        );
        $user->roles()->sync([$role->id]);

        return $user;
    }
}

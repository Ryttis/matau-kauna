<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

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

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'login_type' => 'pareiskejas',
        ]);

        $this->assertGuest();
        $this->assertSessionHasErrors('email');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}

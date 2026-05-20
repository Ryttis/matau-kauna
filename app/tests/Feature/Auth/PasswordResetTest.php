<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = $this->userWithRole(UserRole::ROLE_EMPLOYEE);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reporter_only_user_cannot_request_reset_password_link(): void
    {
        Notification::fake();

        $user = $this->userWithRole(UserRole::ROLE_REPORTER);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_unknown_email_cannot_request_reset_password_link(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'missing@example.test'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_api_staff_user_can_request_reset_password_link(): void
    {
        Notification::fake();

        $user = $this->userWithRole(UserRole::ROLE_EMPLOYEE);

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('ok', true);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_api_reporter_only_user_cannot_request_reset_password_link(): void
    {
        Notification::fake();

        $user = $this->userWithRole(UserRole::ROLE_REPORTER);

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = $this->userWithRole(UserRole::ROLE_EMPLOYEE);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = $this->userWithRole(UserRole::ROLE_EMPLOYEE);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $roleModel = UserRole::firstOrCreate(
            ['role' => $role],
            ['name' => $role],
        );

        $user->roles()->attach($roleModel->id);

        return $user->refresh();
    }
}

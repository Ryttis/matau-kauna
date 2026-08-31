<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_NEW_PASSWORD = 'Str0ng!Pass';

    public function test_password_can_be_updated(): void
    {
        $user = $this->userWithRoles([UserRole::ROLE_EMPLOYEE]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put(route('settings.password'), [
                'current_password' => 'password',
                'password' => self::VALID_NEW_PASSWORD,
                'password_confirmation' => self::VALID_NEW_PASSWORD,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check(self::VALID_NEW_PASSWORD, $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = $this->userWithRoles([UserRole::ROLE_EMPLOYEE]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put(route('settings.password'), [
                'current_password' => 'wrong-password',
                'password' => self::VALID_NEW_PASSWORD,
                'password_confirmation' => self::VALID_NEW_PASSWORD,
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');
    }

    public function test_reporter_only_user_can_update_password_without_current_password(): void
    {
        $user = $this->userWithRoles([UserRole::ROLE_REPORTER]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put(route('settings.password'), [
                'password' => self::VALID_NEW_PASSWORD,
                'password_confirmation' => self::VALID_NEW_PASSWORD,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check(self::VALID_NEW_PASSWORD, $user->refresh()->password));
    }

    public function test_reporter_only_viisp_user_can_create_password_without_current_password(): void
    {
        $user = $this->userWithRoles([UserRole::ROLE_REPORTER], [
            'viisp_personal_code_hash' => bin2hex(random_bytes(16)),
            'viisp_local_password_set_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put(route('settings.password'), [
                'password' => self::VALID_NEW_PASSWORD,
                'password_confirmation' => self::VALID_NEW_PASSWORD,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertTrue(Hash::check(self::VALID_NEW_PASSWORD, $user->password));
        $this->assertNotNull($user->viisp_local_password_set_at);
    }

    public function test_mixed_reporter_and_staff_user_must_provide_current_password(): void
    {
        $user = $this->userWithRoles([UserRole::ROLE_REPORTER, UserRole::ROLE_EMPLOYEE]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put(route('settings.password'), [
                'password' => self::VALID_NEW_PASSWORD,
                'password_confirmation' => self::VALID_NEW_PASSWORD,
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');
    }

    public function test_api_reporter_only_user_can_update_password_without_current_password(): void
    {
        $user = $this->userWithRoles([UserRole::ROLE_REPORTER]);
        Sanctum::actingAs($user);

        $this->putJson('/api/settings/password', [
            'password' => self::VALID_NEW_PASSWORD,
            'password_confirmation' => self::VALID_NEW_PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertTrue(Hash::check(self::VALID_NEW_PASSWORD, $user->refresh()->password));
    }

    private function userWithRoles(array $roles, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'terms_accepted_at' => now(),
            'reporter_report_terms_accepted_at' => now(),
        ], $attributes));
        $roleIds = collect($roles)
            ->map(fn (string $role) => $this->role($role)->id)
            ->all();

        $user->roles()->sync($roleIds);

        return $user->refresh();
    }

    private function role(string $role): UserRole
    {
        $roleModel = UserRole::firstOrCreate(
            ['role' => $role],
            ['name' => $role],
        );

        if (UserRole::isStaffRole($role)) {
            $permission = UserRolePermission::firstOrCreate(
                ['permission' => 'issues.view'],
                ['name' => 'Problemų peržiūra'],
            );
            $roleModel->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $roleModel;
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\Institution;
use App\Models\Invitation;
use App\Models\PasswordPolicy;
use App\Models\User;
use App\Models\UserRole;
use App\Services\InvitationService;
use App\Services\RbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TwoFactorDefaultOnCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Global enforcement OFF — the case that previously left new users without 2FA.
        $this->setGlobalTwoFactor(false);
    }

    public function test_admin_created_staff_user_gets_two_factor_enabled_by_default(): void
    {
        $role = $this->role(UserRole::ROLE_EMPLOYEE);

        $user = app(RbacService::class)->createUser([
            'name' => 'Staff User',
            'surname' => 'Test',
            'email' => 'staff@example.com',
            'password' => 'Password1!',
            'role_id' => $role->id,
        ])->refresh();

        $this->assertTrue((bool) $user->two_factor_enabled);
        $this->assertNotEmpty($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_admin_created_reporter_does_not_get_two_factor(): void
    {
        $role = $this->role(UserRole::ROLE_REPORTER);

        $user = app(RbacService::class)->createUser([
            'name' => 'Reporter User',
            'surname' => 'Test',
            'email' => 'reporter@example.com',
            'password' => 'Password1!',
            'role_id' => $role->id,
        ])->refresh();

        $this->assertFalse((bool) $user->two_factor_enabled);
        $this->assertEmpty($user->two_factor_secret);
    }

    public function test_invited_staff_user_gets_two_factor_enabled_by_default(): void
    {
        $institution = Institution::create(['name' => 'Test Institution']);
        $role = $this->role(UserRole::ROLE_INSTITUTION_ADMIN);

        $invitation = Invitation::create([
            'email' => 'invited@example.com',
            'role_id' => $role->id,
            'institution_id' => $institution->id,
            'token' => Str::random(40),
            'expires_at' => now()->addDay(),
        ]);

        $user = app(InvitationService::class)->registerFromInvitation($invitation, [
            'name' => 'Invited User',
            'email' => 'invited@example.com',
            'password' => 'Password1!',
        ])->refresh();

        $this->assertTrue((bool) $user->two_factor_enabled);
        $this->assertNotEmpty($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_staff_user_can_turn_two_factor_off_in_settings(): void
    {
        $user = $this->staffUser(UserRole::ROLE_EMPLOYEE);
        $this->assertTrue((bool) $user->two_factor_enabled);

        $this->actingAs($user)
            ->patch('/settings/privacy', ['two_factor_enabled' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) $user->refresh()->two_factor_enabled);
    }

    public function test_staff_user_who_opted_out_is_not_challenged_at_login(): void
    {
        $user = $this->staffUser(UserRole::ROLE_EMPLOYEE);
        $user->update(['two_factor_enabled' => false]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password1!',
            'login_type' => 'darbuotojas',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirectContains('/');
        $this->assertFalse((bool) $user->refresh()->two_factor_enabled);
    }

    public function test_opt_out_is_rejected_while_global_two_factor_is_required(): void
    {
        $user = $this->staffUser(UserRole::ROLE_EMPLOYEE);
        $this->setGlobalTwoFactor(true);

        $this->actingAs($user)
            ->patch('/settings/privacy', ['two_factor_enabled' => false])
            ->assertSessionHasErrors('two_factor_enabled');

        $this->assertTrue((bool) $user->refresh()->two_factor_enabled);
    }

    private function staffUser(string $role): User
    {
        $user = app(RbacService::class)->createUser([
            'name' => 'Staff User',
            'surname' => 'Test',
            'email' => 'staff-'.Str::random(8).'@example.com',
            'password' => 'Password1!',
            'role_id' => $this->role($role)->id,
        ]);

        // The /settings routes sit behind the `verified` middleware.
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user->refresh();
    }

    private function role(string $role): UserRole
    {
        return UserRole::firstOrCreate(
            ['role' => $role],
            ['name' => $role]
        );
    }

    private function setGlobalTwoFactor(bool $enabled): void
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

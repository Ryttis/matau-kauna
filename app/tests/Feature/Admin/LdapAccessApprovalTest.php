<?php

namespace Tests\Feature\Admin;

use App\Models\Institution;
use App\Models\LdapAccessRequest;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use App\Services\Ldap\LdapAccessService;
use App\Services\Ldap\LdapIdentity;
use App\Services\Ldap\LdapLinkService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Ldap\InteractsWithFakeLdap;
use Tests\TestCase;

class LdapAccessApprovalTest extends TestCase
{
    use InteractsWithFakeLdap;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableLdap();
        $this->setGlobalTwoFactorRequired(false);
    }

    private function admin(): User
    {
        $permission = UserRolePermission::firstOrCreate(['permission' => 'admin.access'], ['name' => 'Admin access']);
        $role = $this->role(UserRole::ROLE_SYSTEM_ADMIN);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function pending(): LdapAccessRequest
    {
        app(LdapAccessService::class)->observe(new LdapIdentity('NewStaff', 'CN=newstaff', 'Directory Name', 'directory@example.com'), ' TEST-AD ');

        return LdapAccessRequest::sole();
    }

    private function data(): array
    {
        return [
            'name' => 'Jonas', 'surname' => 'Jonaitis', 'email' => 'approved@example.com',
            'role_ids' => [$this->role(UserRole::ROLE_EMPLOYEE)->id, $this->role(UserRole::ROLE_PROBLEM_ADMIN)->id],
            'institution_id' => Institution::create(['name' => 'Test Institution'])->id,
        ];
    }

    private function url(LdapAccessRequest $request, string $action): string
    {
        return '/admin/ldap-access-requests/'.$request->id.'/'.$action;
    }

    public function test_non_admin_cannot_list_approve_or_reject(): void
    {
        $request = $this->pending();
        $this->actingAs(User::factory()->create());
        $this->getJson('/admin/ldap-access-requests')->assertForbidden();
        $this->postJson($this->url($request, 'approve'), $this->data())->assertForbidden();
        $this->postJson($this->url($request, 'reject'))->assertForbidden();
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_admin_lists_and_approves_new_staff_with_roles_institution_and_audit_then_login_succeeds(): void
    {
        $request = $this->pending();
        $admin = $this->admin();
        $data = $this->data();
        $this->actingAs($admin)->getJson('/admin/ldap-access-requests?status=pending')
            ->assertOk()->assertJsonPath('data.0.ldap_username', 'newstaff')->assertJsonPath('data.0.ldap_source', 'test-ad');
        $response = $this->postJson($this->url($request, 'approve'), $data)->assertOk();
        $user = User::findOrFail($response->json('id'));
        $this->assertSame('test-ad', $user->ldap_source);
        $this->assertSame('newstaff', $user->ldap_username);
        $this->assertNotNull($user->ldap_linked_at);
        $this->assertEqualsCanonicalizing($data['role_ids'], $user->roles->pluck('id')->all());
        $this->assertSame($data['institution_id'], $user->institution_id);
        $this->assertDatabaseHas('employees', ['email' => $user->email, 'institution_id' => $data['institution_id']]);
        $this->assertDatabaseHas('ldap_access_requests', ['id' => $request->id, 'status' => 'approved', 'approved_by' => $admin->id, 'linked_user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.ldap.access_request.approved', 'actor_id' => $admin->id]);
        $this->assertNotNull($request->fresh()->approved_at);
        auth()->logout();
        $this->directoryAccount('newstaff', 'Directory-Pass-1');
        $this->post('/login', ['email' => 'newstaff', 'password' => 'Directory-Pass-1', 'login_type' => 'darbuotojas'])
            ->assertRedirect(route('two-factor.challenge'))
            ->assertSessionHas('two_factor_pending_user_id', $user->id);
        $this->assertGuest();
        $code = app(\PragmaRX\Google2FALaravel\Google2FA::class)->getCurrentOtp($user->two_factor_secret);
        $this->post(route('two-factor.challenge.store'), ['code' => $code])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_explicitly_links_existing_user_and_cannot_approve_twice(): void
    {
        $request = $this->pending();
        $user = User::factory()->create();
        $data = $this->data();
        $data['user_id'] = $user->id;
        unset($data['email']);
        $this->actingAs($this->admin())->postJson($this->url($request, 'approve'), $data)->assertOk()->assertJsonPath('id', $user->id);
        $before = User::count();
        $this->postJson($this->url($request, 'approve'), $data)->assertUnprocessable();
        $this->assertSame($before, User::count());
        $this->assertSame(1, User::whereLdapIdentity('test-ad', 'newstaff')->count());
    }

    public function test_email_match_is_not_automatically_linked(): void
    {
        $request = $this->pending();
        $data = $this->data();
        $user = User::factory()->create(['email' => $data['email']]);
        $this->actingAs($this->admin())->postJson($this->url($request, 'approve'), $data)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertNull($user->fresh()->ldap_username);
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_reporter_roles_and_missing_required_institution_are_rejected(): void
    {
        $request = $this->pending();
        $data = $this->data();
        $this->actingAs($this->admin());
        $this->postJson($this->url($request, 'approve'), array_replace($data, ['institution_id' => null]))->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $data['role_ids'] = [$this->role(UserRole::ROLE_REPORTER)->id];
        $this->postJson($this->url($request, 'approve'), $data)->assertUnprocessable()->assertJsonValidationErrors('role_ids');
        $data['role_ids'][] = $this->role(UserRole::ROLE_EMPLOYEE)->id;
        $this->postJson($this->url($request, 'approve'), $data)->assertUnprocessable();
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_reject_preserves_history_and_can_be_approved_later(): void
    {
        $request = $this->pending();
        $admin = $this->admin();
        $before = User::count();
        $this->actingAs($admin)->postJson($this->url($request, 'reject'))->assertNoContent();
        $this->assertSame($before, User::count());
        $this->assertDatabaseHas('ldap_access_requests', ['id' => $request->id, 'status' => 'rejected', 'rejected_by' => $admin->id, 'linked_user_id' => null]);
        $this->assertNotNull($request->fresh()->rejected_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.ldap.access_request.rejected', 'actor_id' => $admin->id]);
        $this->postJson($this->url($request, 'approve'), $this->data())->assertOk();
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->rejected_at);
    }

    public function test_existing_identity_and_existing_target_binding_are_not_overwritten(): void
    {
        $request = $this->pending();
        $existing = $this->ldapStaffUser('different');
        $data = $this->data();
        $this->actingAs($this->admin())->postJson($this->url($request, 'approve'), array_replace($data, ['user_id' => $existing->id]))->assertUnprocessable();
        $this->ldapStaffUser('NEWSTAFF');
        $before = User::count();
        $this->postJson($this->url($request, 'approve'), $data)->assertUnprocessable();
        $this->assertSame($before, User::count());
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_link_failure_rolls_back_user_roles_employee_and_decision(): void
    {
        $request = $this->pending();
        $actor = $this->admin();
        $data = $this->data();
        $before = User::count();
        $links = \Mockery::mock(LdapLinkService::class)->makePartial();
        $links->shouldReceive('link')->once()->andThrow(new \RuntimeException('Simulated linking failure'));
        $this->app->instance(LdapLinkService::class, $links);
        try {
            app(LdapAccessService::class)->approve($request, $data, $actor);
            $this->fail('Expected linking failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated linking failure', $e->getMessage());
        }
        $this->assertSame($before, User::count());
        $this->assertDatabaseMissing('employees', ['email' => $data['email']]);
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertNull($request->fresh()->linked_user_id);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'auth.ldap.access_request.approved']);
    }

    public function test_competing_approval_cannot_pass_identity_lock_on_another_connection(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL concurrency guard.');
        }
        $request = $this->pending();
        $actor = $this->admin();
        $data = $this->data();
        $original = DB::getDefaultConnection();
        config(['database.connections.ldap_competitor' => config('database.connections.'.$original)]);
        // RefreshDatabase's open transaction owns the same lock an approval holds.
        app(LdapLinkService::class)->lockIdentity($request->ldap_source, $request->ldap_username);
        try {
            DB::setDefaultConnection('ldap_competitor');
            DB::statement("SET lock_timeout = '100ms'");
            try {
                app(LdapAccessService::class)->approve($request, $data, $actor);
                $this->fail('Competing approval must wait for the identity lock.');
            } catch (QueryException $e) {
                $this->assertSame('55P03', $e->errorInfo[0]);
            }
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('ldap_competitor');
        }
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame(0, User::whereLdapIdentity('test-ad', 'newstaff')->count());
        app(LdapAccessService::class)->approve($request, $data, $actor);
        $this->assertSame(1, User::whereLdapIdentity('test-ad', 'newstaff')->count());
    }
}

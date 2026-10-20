<?php

namespace Tests\Feature\Auth;

use App\Models\LdapAccessRequest;
use App\Models\User;
use App\Services\Ldap\LdapAuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ldap\InteractsWithFakeLdap;
use Tests\TestCase;

class LdapAccessRequestTest extends TestCase
{
    use InteractsWithFakeLdap;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableLdap();
        $this->setGlobalTwoFactorRequired(false);
    }

    private function login(string $password = 'Directory-Pass-1')
    {
        return $this->postJson('/api/login', ['email' => 'newstaff', 'password' => $password, 'login_type' => 'darbuotojas']);
    }

    public function test_verified_unmapped_identity_is_observed_without_user_or_token_and_repeated_attempt_updates_it(): void
    {
        $this->directoryAccount('newstaff', 'Directory-Pass-1');
        $before = User::count();
        $this->login()->assertUnprocessable()->assertJsonValidationErrors(['email' => LdapAuthenticationException::APPROVAL_MESSAGE]);
        $request = LdapAccessRequest::sole();
        $first = $request->first_seen_at;
        $this->travel(2)->minutes();
        $this->login()->assertUnprocessable();
        $this->assertDatabaseCount('ldap_access_requests', 1);
        $this->assertTrue($request->fresh()->last_seen_at->greaterThan($first));
        $this->assertTrue($request->fresh()->first_seen_at->equalTo($first));
        $this->assertSame($before, User::count());
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.ldap.access_request.observed']);
    }

    public function test_linked_identity_keeps_existing_api_login_and_does_not_create_request(): void
    {
        $user = $this->ldapStaffUser('newstaff');
        $this->directoryAccount('newstaff', 'Directory-Pass-1');
        $this->login()->assertOk();
        $this->assertDatabaseCount('ldap_access_requests', 0);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_bad_password_and_unavailable_directory_do_not_create_requests(): void
    {
        $this->directoryAccount('newstaff', 'Directory-Pass-1');
        $this->login('wrong')->assertUnprocessable();
        $this->assertDatabaseCount('ldap_access_requests', 0);
        $this->fakeLdap()->bindFailure = LdapAuthenticationException::unavailable('Unavailable');
        $this->login()->assertUnprocessable();
        $this->assertDatabaseCount('ldap_access_requests', 0);
    }

    public function test_duplicate_mapping_remains_a_mapping_failure_without_request(): void
    {
        $this->ldapStaffUser('newstaff', ['email' => 'one@example.com']);
        $this->ldapStaffUser('NEWSTAFF', ['email' => 'two@example.com']);
        $this->directoryAccount('newstaff', 'Directory-Pass-1');
        $this->login()->assertUnprocessable()->assertJsonValidationErrors(['email' => __('auth.failed', [], 'lt')]);
        $this->assertDatabaseCount('ldap_access_requests', 0);
    }

    public function test_rejected_identity_stays_rejected_on_subsequent_valid_attempt(): void
    {
        $this->directoryAccount('newstaff', 'Directory-Pass-1');
        $this->login()->assertUnprocessable();
        LdapAccessRequest::sole()->update(['status' => 'rejected', 'rejected_at' => now()]);
        $this->login()->assertUnprocessable()->assertJsonValidationErrors(['email' => LdapAuthenticationException::APPROVAL_MESSAGE]);
        $this->assertSame('rejected', LdapAccessRequest::sole()->status);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ProblemStatus;
use App\Models\EmailNotificationTemplate;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\Invitation;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use App\Notifications\InvitationNotification;
use App\Services\ProblemAssignmentNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailNotificationTemplateDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_institution_assignment_template_suppresses_admin_email(): void
    {
        $this->disableTemplate(EmailNotificationTemplate::KEY_INSTITUTION_ASSIGNMENT);

        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $admin = $this->staffUser($institution, UserRole::ROLE_INSTITUTION_ADMIN, [
            'issues.view',
        ], [
            'email' => 'admin@example.test',
            'email_updates_institution_admin' => true,
        ]);
        $problem = $this->problem([
            'institution_id' => $institution->id,
        ]);

        Mail::shouldReceive('send')->never();

        app(ProblemAssignmentNotifier::class)->notifyInstitutionOfAssignment([$problem->id], $institution->id);
    }

    public function test_disabled_employee_assignment_template_suppresses_employee_email(): void
    {
        $this->disableTemplate(EmailNotificationTemplate::KEY_EMPLOYEE_ASSIGNMENT);

        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $employee = Employee::create([
            'name' => 'Test',
            'surname' => 'Employee',
            'email' => 'employee@example.test',
            'institution_id' => $institution->id,
        ]);
        $this->staffUser($institution, UserRole::ROLE_EMPLOYEE, [], [
            'email' => 'employee@example.test',
            'email_notifications_enabled' => true,
        ]);
        $problem = $this->problem([
            'institution_id' => $institution->id,
        ]);

        Mail::shouldReceive('send')->never();

        app(ProblemAssignmentNotifier::class)->notifyEmployeeOfAssignment($problem, $employee);
    }

    public function test_disabled_manual_assign_template_skips_email_but_keeps_assignment_action(): void
    {
        $this->disableTemplate(EmailNotificationTemplate::KEY_MANUAL_ASSIGN_TO_INSTITUTION_EMAIL);

        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $user = $this->staffUser($institution, UserRole::ROLE_PROBLEM_ADMIN, [
            'issues.view',
            'issues.publish',
        ]);
        $problem = $this->problem([
            'status' => ProblemStatus::NEW->value,
            'institution_id' => null,
        ]);

        Mail::shouldReceive('send')->never();

        $this
            ->actingAs($user)
            ->post(route('problems.assign.email'), [
                'email' => 'rangovas@example.test',
                'problem_ids' => [$problem->id],
                'institution_id' => $institution->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $problem->refresh();
        $this->assertSame($institution->id, $problem->institution_id);
        $this->assertSame(ProblemStatus::RESOLVED->value, $problem->status);
    }

    public function test_disabled_institution_problem_share_template_skips_email(): void
    {
        $this->disableTemplate(EmailNotificationTemplate::KEY_INSTITUTION_PROBLEM_SHARE);

        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $user = $this->staffUser($institution, UserRole::ROLE_INSTITUTION_ADMIN, [
            'issues.view',
            'issues.assign',
        ]);
        $problem = $this->problem([
            'institution_id' => $institution->id,
        ]);

        Mail::shouldReceive('send')->never();

        $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.sendEmail', $problem), [
                'email' => 'rangovas@example.test',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    public function test_disabled_verification_and_password_reset_templates_suppress_notifications(): void
    {
        $this->disableTemplate(EmailNotificationTemplate::KEY_VERIFY_EMAIL);
        $this->disableTemplate(EmailNotificationTemplate::KEY_PASSWORD_RESET);

        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $user->sendEmailVerificationNotification();
        $user->sendPasswordResetNotification('test-token');

        Notification::assertNothingSent();
    }

    public function test_disabled_invitation_template_suppresses_invitation_notification(): void
    {
        $this->disableTemplate(EmailNotificationTemplate::KEY_INSTITUTION_INVITATION);

        Notification::fake();

        $invitation = Invitation::create([
            'email' => 'invitee@example.test',
            'institution_id' => Institution::create(['name' => 'Kauno tvarkymas'])->id,
            'role_id' => $this->role(UserRole::ROLE_INSTITUTION_ADMIN)->id,
            'token' => 'test-token',
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new InvitationNotification($invitation));

        Notification::assertNothingSent();
    }

    private function disableTemplate(string $key): void
    {
        EmailNotificationTemplate::updateOrCreate(
            ['key' => $key],
            [
                'name' => $key,
                'subject' => 'Disabled subject',
                'body' => 'Disabled body',
                'is_enabled' => false,
            ]
        );
    }

    private function problem(array $attributes = []): Problem
    {
        return Problem::create(array_merge([
            'status' => ProblemStatus::IN_REVIEW->value,
            'category' => 'Keliai',
            'address' => 'Laisves al., Kaunas',
            'description' => 'Bandomoji problema',
            'lat' => 54.8985,
            'lng' => 23.9036,
        ], $attributes));
    }

    private function staffUser(Institution $institution, string $roleKey, array $permissions = [], array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'institution_id' => $institution->id,
        ], $attributes));

        $role = $this->role($roleKey);
        foreach ($permissions as $key) {
            $permission = UserRolePermission::firstOrCreate(
                ['permission' => $key],
                ['name' => $key],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $role->users()->attach($user->id);

        return $user->fresh();
    }

    private function role(string $roleKey): UserRole
    {
        return UserRole::firstOrCreate(
            ['role' => $roleKey],
            ['name' => $roleKey],
        );
    }
}

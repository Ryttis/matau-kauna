<?php

namespace Tests\Feature\Problems;

use App\Enums\ProblemStatus;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\ProblemReviewHistory;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProblemAssignInstitutionEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_problem_admin_manual_assign_email_resolves_problem(): void
    {
        Mail::fake();

        $institution = Institution::create(['name' => 'Think BIG']);
        $user = $this->staffUser($institution, ['issues.view', 'issues.publish']);
        $problem = Problem::create([
            'status' => ProblemStatus::NEW->value,
            'category' => 'Keliai',
            'address' => 'Laisves al., Kaunas',
            'description' => 'Bandomoji problema',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'institution_id' => null,
            'completion_deadline' => now()->addDay()->toDateString(),
            'deadline_status' => 'approaching',
            'deadline_reminder_sent_at' => now(),
        ]);

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
        $this->assertNull($problem->deadline_status);
        $this->assertNull($problem->deadline_reminder_sent_at);
        $this->assertDatabaseHas('problem_review_history', [
            'problem_id' => $problem->id,
            'user_id' => $user->id,
            'status' => ProblemStatus::RESOLVED->value,
            'status_changed' => true,
        ]);
    }

    public function test_problem_admin_manual_assign_email_resolves_merged_problem_group(): void
    {
        Mail::fake();

        $institution = Institution::create(['name' => 'Think BIG']);
        $user = $this->staffUser($institution, ['issues.view', 'issues.publish']);
        $root = Problem::create([
            'status' => ProblemStatus::NEW->value,
            'category' => 'Keliai',
            'address' => 'Laisves al., Kaunas',
            'description' => 'Pagrindine problema',
            'lat' => 54.8985,
            'lng' => 23.9036,
        ]);
        $child = Problem::create([
            'status' => ProblemStatus::IN_REVIEW->value,
            'category' => 'Keliai',
            'address' => 'Laisves al., Kaunas',
            'description' => 'Sujungta problema',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'merged_into_problem_id' => $root->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.assign.email'), [
                'email' => 'rangovas@example.test',
                'problem_ids' => [$child->id],
                'institution_id' => $institution->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(ProblemStatus::RESOLVED->value, $root->fresh()->status);
        $this->assertSame(ProblemStatus::RESOLVED->value, $child->fresh()->status);
        $this->assertSame($institution->id, $root->fresh()->institution_id);
        $this->assertSame($institution->id, $child->fresh()->institution_id);
        $this->assertSame(1, ProblemReviewHistory::where('problem_id', $root->id)
            ->where('status', ProblemStatus::RESOLVED->value)
            ->count());
    }

    public function test_manual_assign_information_email_keeps_problem_status(): void
    {
        Mail::fake();

        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $user = $this->staffUser($institution, ['issues.view', 'issues.assign']);
        $problem = Problem::create([
            'status' => ProblemStatus::IN_REVIEW->value,
            'category' => 'Keliai',
            'address' => 'Laisves al., Kaunas',
            'description' => 'Bandomoji problema',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'institution_id' => $institution->id,
            'completion_deadline' => now()->addDay()->toDateString(),
            'deadline_status' => 'approaching',
            'deadline_reminder_sent_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.sendEmail', $problem), [
                'email' => 'rangovas@example.test',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $problem->refresh();
        $this->assertSame(ProblemStatus::IN_REVIEW->value, $problem->status);
        $this->assertSame('approaching', $problem->deadline_status);
        $this->assertNotNull($problem->deadline_reminder_sent_at);

        $this->assertDatabaseMissing('problem_review_history', [
            'problem_id' => $problem->id,
            'user_id' => $user->id,
            'status' => ProblemStatus::RESOLVED->value,
            'status_changed' => true,
        ]);
    }

    public function test_manual_assign_information_email_keeps_merged_problem_group_statuses(): void
    {
        Mail::fake();

        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $user = $this->staffUser($institution, ['issues.view', 'issues.assign']);
        $root = Problem::create([
            'status' => ProblemStatus::IN_REVIEW->value,
            'category' => 'Keliai',
            'address' => 'Laisves al., Kaunas',
            'description' => 'Pagrindine problema',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'institution_id' => $institution->id,
        ]);
        $child = Problem::create([
            'status' => ProblemStatus::NEW->value,
            'category' => 'Keliai',
            'address' => 'Laisves al., Kaunas',
            'description' => 'Sujungta problema',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'institution_id' => $institution->id,
            'merged_into_problem_id' => $root->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.sendEmail', $child), [
                'email' => 'rangovas@example.test',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(ProblemStatus::IN_REVIEW->value, $root->fresh()->status);
        $this->assertSame(ProblemStatus::NEW->value, $child->fresh()->status);
        $this->assertSame(0, ProblemReviewHistory::where('problem_id', $root->id)
            ->where('status', ProblemStatus::RESOLVED->value)
            ->count());
    }

    private function staffUser(Institution $institution, array $permissions): User
    {
        $user = User::factory()->create([
            'institution_id' => $institution->id,
        ]);
        $role = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_INSTITUTION_ADMIN],
            ['name' => 'Istaigos administratorius'],
        );
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
}

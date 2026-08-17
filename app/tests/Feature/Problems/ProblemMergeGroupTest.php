<?php

namespace Tests\Feature\Problems;

use App\Enums\ProblemStatus;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\InstitutionDvsConfig;
use App\Models\InstitutionGisConfig;
use App\Models\Problem;
use App\Models\ProblemResolutionTermRule;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProblemMergeGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_group_root_stays_parent_when_merging_from_a_new_problem(): void
    {
        $user = $this->staffUser(UserRole::ROLE_PROBLEM_ADMIN, ['issues.view', 'issues.merge', 'issues.publish']);
        $root = $this->problem(['created_at' => now()->subDays(5)]);
        $child = $this->problem(['merged_into_problem_id' => $root->id, 'created_at' => now()->subDays(4)]);
        $newProblem = $this->problem(['created_at' => now()]);

        $this
            ->actingAs($user)
            ->post(route('problems.merge.store', $newProblem), ['problem_ids' => [$root->id]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('problems.show', $root->id));

        $this->assertNull($root->fresh()->merged_into_problem_id);
        $this->assertSame($root->id, $child->fresh()->merged_into_problem_id);
        $this->assertSame($root->id, $newProblem->fresh()->merged_into_problem_id);
        $this->assertStringContainsString($root->internal_number, session('success'));
    }

    public function test_current_problem_stays_parent_when_it_already_has_children(): void
    {
        $user = $this->staffUser(UserRole::ROLE_PROBLEM_ADMIN, ['issues.view', 'issues.merge', 'issues.publish']);
        $root = $this->problem(['created_at' => now()->subDay()]);
        $this->problem(['merged_into_problem_id' => $root->id]);
        $other = $this->problem(['created_at' => now()->subDays(10)]);

        $this
            ->actingAs($user)
            ->post(route('problems.merge.store', $root), ['problem_ids' => [$other->id]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('problems.show', $root->id));

        $this->assertNull($root->fresh()->merged_into_problem_id);
        $this->assertSame($root->id, $other->fresh()->merged_into_problem_id);
    }

    public function test_current_problem_becomes_parent_when_no_selected_problem_is_a_group_root(): void
    {
        $user = $this->staffUser(UserRole::ROLE_PROBLEM_ADMIN, ['issues.view', 'issues.merge', 'issues.publish']);
        $current = $this->problem(['created_at' => now()]);
        $other = $this->problem(['created_at' => now()->subDays(10)]);

        $this
            ->actingAs($user)
            ->post(route('problems.merge.store', $current), ['problem_ids' => [$other->id]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('problems.show', $current->id));

        $this->assertNull($current->fresh()->merged_into_problem_id);
        $this->assertSame($current->id, $other->fresh()->merged_into_problem_id);
    }

    public function test_merged_children_inherit_institution_status_and_deadline(): void
    {
        $user = $this->staffUser(UserRole::ROLE_PROBLEM_ADMIN, ['issues.view', 'issues.merge', 'issues.publish']);
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $otherInstitution = Institution::create(['name' => 'Kita istaiga']);
        $employee = Employee::create([
            'institution_id' => $otherInstitution->id,
            'name' => 'Jonas',
            'surname' => 'Jonaitis',
            'email' => 'jonas@kitaistaiga.lt',
        ]);

        $root = $this->problem([
            'institution_id' => $institution->id,
            'status' => ProblemStatus::IN_REVIEW->value,
            'completion_deadline' => now()->addDays(7)->toDateString(),
        ]);
        $other = $this->problem([
            'institution_id' => $otherInstitution->id,
            'assigned_employee_id' => $employee->id,
            'status' => ProblemStatus::NEW->value,
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.merge.store', $root), ['problem_ids' => [$other->id]])
            ->assertSessionHasNoErrors();

        $fresh = $other->fresh();
        $this->assertSame($root->id, $fresh->merged_into_problem_id);
        $this->assertSame($institution->id, $fresh->institution_id);
        $this->assertSame(ProblemStatus::IN_REVIEW->value, $fresh->status);
        $this->assertSame(
            $root->fresh()->completion_deadline->toDateString(),
            $fresh->completion_deadline->toDateString()
        );
        $this->assertNull($fresh->assigned_employee_id);
        $this->assertNull($fresh->assigned_institution_team_id);
    }

    public function test_merge_flattens_descendants_into_a_single_level(): void
    {
        $user = $this->staffUser(UserRole::ROLE_PROBLEM_ADMIN, ['issues.view', 'issues.merge', 'issues.publish']);
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);

        $oldRoot = $this->problem([
            'institution_id' => $institution->id,
            'created_at' => now()->subDays(9),
        ]);
        $oldChild = $this->problem([
            'institution_id' => null,
            'merged_into_problem_id' => $oldRoot->id,
            'created_at' => now()->subDays(8),
        ]);
        $current = $this->problem(['institution_id' => $institution->id, 'created_at' => now()]);

        $this
            ->actingAs($user)
            ->post(route('problems.merge.store', $current), ['problem_ids' => [$oldRoot->id]])
            ->assertSessionHasNoErrors();

        // Senoji šaknis laimi, o jos vaikas lieka tiesiogiai po ja - be 2 lygių grandinės.
        $this->assertNull($oldRoot->fresh()->merged_into_problem_id);
        $this->assertSame($oldRoot->id, $oldChild->fresh()->merged_into_problem_id);
        $this->assertSame($oldRoot->id, $current->fresh()->merged_into_problem_id);
        $this->assertSame($institution->id, $oldChild->fresh()->institution_id);

        $groupIds = $oldRoot->fresh()->getMergeGroupIds();
        sort($groupIds);
        $expected = [$oldRoot->id, $oldChild->id, $current->id];
        sort($expected);
        $this->assertSame($expected, $groupIds);
    }

    public function test_institution_scoped_user_cannot_partially_merge_out_of_scope_problems(): void
    {
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $user = $this->staffUser(
            UserRole::ROLE_INSTITUTION_ADMIN,
            ['issues.view', 'issues.merge'],
            $institution
        );

        $current = $this->problem(['institution_id' => $institution->id]);
        $outOfScope = $this->problem(['institution_id' => null]);

        $this
            ->actingAs($user)
            ->post(route('problems.merge.store', $current), ['problem_ids' => [$outOfScope->id]])
            ->assertForbidden();

        $this->assertNull($outOfScope->fresh()->merged_into_problem_id);
    }

    public function test_merged_child_assign_page_exposes_gis_and_dvs_after_inheriting_institution(): void
    {
        $institution = Institution::create(['name' => 'Integracijos istaiga']);
        InstitutionGisConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'generate_token_url' => 'https://example.test/portal/sharing/rest/generateToken',
            'technical_username' => 'arcgis-user',
            'technical_password' => 'secret',
            'client_type' => 'referer',
            'token_expiration_minutes' => 60,
            'feature_server_url' => 'https://example.test/FeatureServer/1',
            'attachment_url' => 'https://example.test/FeatureServer/1',
            'wkid' => 3346,
        ]);
        InstitutionDvsConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'complaint_api' => InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI,
            'gaunami_dokumentai_url' => 'https://example.test/api/GaunamiDokumentai',
        ]);

        $mergeUser = $this->staffUser(UserRole::ROLE_PROBLEM_ADMIN, ['issues.view', 'issues.merge', 'issues.publish']);
        $root = $this->problem(['institution_id' => $institution->id]);
        $child = $this->problem(['institution_id' => null]);

        $this
            ->actingAs($mergeUser)
            ->post(route('problems.merge.store', $root), ['problem_ids' => [$child->id]])
            ->assertSessionHasNoErrors();

        $institutionAdmin = $this->staffUser(
            UserRole::ROLE_INSTITUTION_ADMIN,
            ['issues.view', 'issues.assign', 'invitations.manage'],
            $institution
        );

        $this
            ->actingAs($institutionAdmin)
            ->get(route('problems.assignInstitution', $child))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Problems/AssignInstitution')
                ->where('gisAvailable', true)
                ->where('dvsAvailable', true)
                ->etc());
    }

    public function test_reassign_institution_applies_to_the_whole_merge_group(): void
    {
        $from = Institution::create(['name' => 'Senoji istaiga']);
        $to = Institution::create(['name' => 'Naujoji istaiga']);
        $user = $this->staffUser(
            UserRole::ROLE_INSTITUTION_ADMIN,
            ['issues.view', 'issues.edit', 'issues.reassign'],
            $from
        );

        $root = $this->problem(['institution_id' => $from->id]);
        $child = $this->problem(['institution_id' => $from->id, 'merged_into_problem_id' => $root->id]);

        $this
            ->actingAs($user)
            ->post(route('problems.reassignInstitution', $root), ['institution_id' => $to->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($to->id, $root->fresh()->institution_id);
        $this->assertSame($to->id, $child->fresh()->institution_id);
    }

    public function test_admin_assign_applies_institution_to_the_whole_merge_group(): void
    {
        $user = $this->staffUser(UserRole::ROLE_SYSTEM_ADMIN, ['issues.view', 'issues.edit', 'issues.publish']);
        $institution = Institution::create(['name' => 'Naujoji istaiga']);

        $root = $this->problem(['status' => ProblemStatus::UNREGISTERED->value]);
        $child = $this->problem([
            'status' => ProblemStatus::UNREGISTERED->value,
            'merged_into_problem_id' => $root->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.assign.store'), [
                'problem_ids' => [$root->id],
                'institution_id' => $institution->id,
                'status' => ProblemStatus::NEW->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($institution->id, $root->fresh()->institution_id);
        $this->assertSame($institution->id, $child->fresh()->institution_id);
        $this->assertSame(ProblemStatus::NEW->value, $child->fresh()->status);
    }

    public function test_admin_assign_gives_the_whole_group_one_resolution_deadline(): void
    {
        $user = $this->staffUser(UserRole::ROLE_SYSTEM_ADMIN, ['issues.view', 'issues.edit', 'issues.publish']);
        $institution = Institution::create(['name' => 'Naujoji istaiga']);
        ProblemResolutionTermRule::create([
            'category' => 'Keliai',
            'days' => 10,
            'is_active' => true,
        ]);

        $root = $this->problem(['category' => 'Keliai']);
        $child = $this->problem(['category' => 'Apšvietimas', 'merged_into_problem_id' => $root->id]);

        $this
            ->actingAs($user)
            ->post(route('problems.assign.store'), [
                'problem_ids' => [$root->id],
                'institution_id' => $institution->id,
            ])
            ->assertSessionHasNoErrors();

        $rootDeadline = $root->fresh()->completion_deadline;
        $this->assertNotNull($rootDeadline);
        $this->assertSame($rootDeadline->toDateString(), $child->fresh()->completion_deadline?->toDateString());
    }

    public function test_api_merge_rejects_resolved_problems(): void
    {
        $user = $this->staffUser(UserRole::ROLE_PROBLEM_ADMIN, ['issues.view', 'issues.merge', 'issues.publish']);
        $current = $this->problem();
        $resolved = $this->problem(['status' => ProblemStatus::RESOLVED->value]);

        $this
            ->actingAs($user)
            ->postJson("/api/internal/problems/{$current->id}/merge", [
                'problem_ids' => [$resolved->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Negalima apjungti išnagrinėtų problemų.');

        $this->assertNull($resolved->fresh()->merged_into_problem_id);
    }

    public function test_sync_command_repairs_existing_groups(): void
    {
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);

        $root = $this->problem([
            'institution_id' => $institution->id,
            'status' => ProblemStatus::IN_REVIEW->value,
            'completion_deadline' => now()->addDays(3)->toDateString(),
        ]);
        $child = $this->problem([
            'institution_id' => null,
            'status' => ProblemStatus::NEW->value,
            'merged_into_problem_id' => $root->id,
        ]);
        $grandChild = $this->problem([
            'institution_id' => null,
            'status' => ProblemStatus::NEW->value,
            'merged_into_problem_id' => $child->id,
        ]);

        $this->artisan('problems:sync-merge-groups')->assertSuccessful();

        $this->assertSame($root->id, $child->fresh()->merged_into_problem_id);
        $this->assertSame($root->id, $grandChild->fresh()->merged_into_problem_id);

        foreach ([$child, $grandChild] as $problem) {
            $fresh = $problem->fresh();
            $this->assertSame($institution->id, $fresh->institution_id);
            $this->assertSame(ProblemStatus::IN_REVIEW->value, $fresh->status);
            $this->assertSame(
                $root->fresh()->completion_deadline->toDateString(),
                $fresh->completion_deadline->toDateString()
            );
        }
    }

    private function problem(array $attributes = []): Problem
    {
        return Problem::create(array_merge([
            'status' => ProblemStatus::NEW->value,
            'category' => 'Keliai',
            'address' => 'Laisves al. 10, Kaunas',
            'description' => 'Bandomoji problema.',
            'lat' => 54.8985,
            'lng' => 23.9036,
        ], $attributes));
    }

    private function staffUser(string $role, array $permissions, ?Institution $institution = null): User
    {
        $user = User::factory()->create([
            'institution_id' => $institution?->id,
        ]);
        $userRole = UserRole::firstOrCreate(['role' => $role], ['name' => $role]);

        foreach ($permissions as $key) {
            $permission = UserRolePermission::firstOrCreate(
                ['permission' => $key],
                ['name' => $key],
            );
            $userRole->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $userRole->users()->attach($user->id);

        return $user->fresh();
    }
}

<?php

namespace Tests\Feature\Problems;

use App\Enums\ProblemStatus;
use App\Models\DataRetentionRule;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use App\Services\ArchiveService;
use App\Services\DataRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProblemArchiveMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_merged_child_archives_only_that_problem(): void
    {
        $user = $this->staffUser(UserRole::ROLE_PROBLEM_ADMIN, ['issues.view', 'issues.delete']);
        $root = $this->problem();
        $child = $this->problem(['merged_into_problem_id' => $root->id]);
        $sibling = $this->problem(['merged_into_problem_id' => $root->id]);

        $this
            ->actingAs($user)
            ->delete(route('problems.destroy', $child))
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($child->fresh()->is_archived);
        $this->assertFalse($root->fresh()->is_archived);
        $this->assertFalse($sibling->fresh()->is_archived);
        $this->assertSame($root->id, $sibling->fresh()->merged_into_problem_id);
    }

    public function test_deleting_group_root_archives_only_root_and_detaches_children(): void
    {
        $user = $this->staffUser(UserRole::ROLE_PROBLEM_ADMIN, ['issues.view', 'issues.delete']);
        $root = $this->problem();
        $childOne = $this->problem(['merged_into_problem_id' => $root->id]);
        $childTwo = $this->problem(['merged_into_problem_id' => $root->id]);

        $this
            ->actingAs($user)
            ->delete(route('problems.destroy', $root))
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($root->fresh()->is_archived);

        foreach ([$childOne, $childTwo] as $child) {
            $fresh = $child->fresh();
            $this->assertFalse($fresh->is_archived, 'Prijungta problema neturi buti archyvuota.');
            $this->assertNull($fresh->merged_into_problem_id, 'Prijungta problema turi buti atskirta.');
        }
    }

    public function test_bulk_destroy_archives_only_selected_problems(): void
    {
        $user = $this->staffUser(UserRole::ROLE_PROBLEM_ADMIN, ['issues.view', 'issues.delete']);
        $root = $this->problem();
        $child = $this->problem(['merged_into_problem_id' => $root->id]);
        $other = $this->problem();

        $this
            ->actingAs($user)
            ->post(route('problems.bulkDestroy'), ['problem_ids' => [$child->id]])
            ->assertSessionHasNoErrors();

        $this->assertTrue($child->fresh()->is_archived);
        $this->assertFalse($root->fresh()->is_archived);
        $this->assertFalse($other->fresh()->is_archived);
    }

    public function test_dashboard_list_excludes_archived_merged_children(): void
    {
        $user = $this->staffUser(UserRole::ROLE_PROBLEM_ADMIN, ['issues.view', 'issues.delete']);
        $root = $this->problem();
        $visibleChild = $this->problem(['merged_into_problem_id' => $root->id]);
        $archivedChild = $this->problem([
            'merged_into_problem_id' => $root->id,
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($root, $visibleChild, $archivedChild) {
                $rows = collect($page->toArray()['props']['problems']['data']);
                $rootRow = $rows->firstWhere('id', $root->id);

                $this->assertNotNull($rootRow, 'Pagrindine problema turi buti sarase.');

                $mergedIds = collect($rootRow['merged_problems'] ?? [])->pluck('id')->all();
                $this->assertContains($visibleChild->id, $mergedIds);
                $this->assertNotContains($archivedChild->id, $mergedIds);
            });
    }

    public function test_archive_list_includes_merged_children_with_parent_number(): void
    {
        $root = $this->problem(['is_archived' => true, 'archived_at' => now()]);
        $child = $this->problem([
            'merged_into_problem_id' => $root->id,
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $problems = collect(app(ArchiveService::class)->getIndexData()['problems']);

        $childRow = $problems->firstWhere('id', $child->id);
        $this->assertNotNull($childRow, 'Prijungta archyvuota problema turi matytis archyve.');
        $this->assertSame($root->internal_number, $childRow['merged_into_internal_number']);

        $rootRow = $problems->firstWhere('id', $root->id);
        $this->assertNotNull($rootRow);
        $this->assertNull($rootRow['merged_into_internal_number']);
    }

    public function test_restoring_child_detaches_it_when_parent_stays_archived(): void
    {
        $admin = $this->staffUser(UserRole::ROLE_SYSTEM_ADMIN, ['admin.access']);
        $root = $this->problem(['is_archived' => true, 'archived_at' => now()]);
        $child = $this->problem([
            'merged_into_problem_id' => $root->id,
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->post("/admin/archive/problems/{$child->id}/restore")
            ->assertOk();

        $fresh = $child->fresh();
        $this->assertFalse($fresh->is_archived);
        $this->assertNull($fresh->merged_into_problem_id, 'Atkurta problema turi buti atskirta nuo archyvuotos pagrindines.');
    }

    public function test_restoring_child_keeps_merge_link_when_parent_is_active(): void
    {
        $admin = $this->staffUser(UserRole::ROLE_SYSTEM_ADMIN, ['admin.access']);
        $root = $this->problem();
        $child = $this->problem([
            'merged_into_problem_id' => $root->id,
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->post("/admin/archive/problems/{$child->id}/restore")
            ->assertOk();

        $fresh = $child->fresh();
        $this->assertFalse($fresh->is_archived);
        $this->assertSame($root->id, $fresh->merged_into_problem_id);
    }

    public function test_retention_purge_keeps_non_archived_group_members(): void
    {
        DataRetentionRule::create([
            'entity_type' => DataRetentionRule::ENTITY_PROBLEM,
            'delete_after_days' => 30,
            'is_active' => true,
        ]);

        $root = $this->problem();
        $oldArchivedChild = $this->problem([
            'merged_into_problem_id' => $root->id,
            'is_archived' => true,
            'archived_at' => now()->subDays(90),
        ]);

        $deleted = app(DataRetentionService::class)->runDeletionFor(DataRetentionRule::ENTITY_PROBLEM);

        $this->assertSame(1, $deleted);
        $this->assertNull(Problem::find($oldArchivedChild->id));
        $this->assertNotNull(Problem::find($root->id), 'Gyva grupes nare neturi buti istrinta kartu.');
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

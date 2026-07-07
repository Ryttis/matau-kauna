<?php

namespace Tests\Feature\Problems;

use App\Enums\ProblemReviewCommentVisibility;
use App\Enums\ProblemStatus;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\ProblemReviewHistory;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProblemStatusNoOpTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_entry_with_same_status_is_not_marked_as_status_change(): void
    {
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $user = $this->staffUser($institution, ['issues.view', 'issues.edit']);
        $statusUpdatedAt = now()->subDay()->startOfSecond();
        $problem = $this->problem([
            'institution_id' => $institution->id,
            'status' => ProblemStatus::IN_REVIEW->value,
            'status_updated_at' => $statusUpdatedAt,
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.reviewProgress.store', $problem), [
                'status' => ProblemStatus::IN_REVIEW->value,
                'comment' => 'Papildomas komentaras',
                'visibility' => ProblemReviewCommentVisibility::Public->value,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $entry = ProblemReviewHistory::query()->where('problem_id', $problem->id)->firstOrFail();
        $this->assertFalse((bool) $entry->status_changed);
        $this->assertSame($statusUpdatedAt->toDateTimeString(), $problem->fresh()->status_updated_at->toDateTimeString());

        $this
            ->actingAs($user)
            ->getJson(route('problems.reviewProgress', $problem))
            ->assertOk()
            ->assertJsonPath('reviewEntries.0.status_changed', false);
    }

    public function test_review_entry_update_recomputes_status_changed_when_status_matches_problem(): void
    {
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $user = $this->staffUser($institution, ['issues.view', 'issues.edit']);
        $problem = $this->problem([
            'institution_id' => $institution->id,
            'status' => ProblemStatus::RESOLVED->value,
            'status_updated_at' => now()->subDay(),
        ]);
        $entry = ProblemReviewHistory::create([
            'problem_id' => $problem->id,
            'user_id' => $user->id,
            'status' => ProblemStatus::IN_REVIEW->value,
            'status_changed' => true,
            'responsible' => 'Test User',
            'comment' => 'Senas komentaras',
            'visibility' => ProblemReviewCommentVisibility::Public->value,
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.reviewProgress.update', [$problem, $entry]), [
                'status' => ProblemStatus::RESOLVED->value,
                'comment' => 'Atnaujintas komentaras',
                'visibility' => ProblemReviewCommentVisibility::Public->value,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $entry->refresh();
        $this->assertSame(ProblemStatus::RESOLVED->value, $entry->status);
        $this->assertFalse((bool) $entry->status_changed);
    }

    public function test_api_status_update_to_same_status_does_not_create_history_or_touch_timestamp(): void
    {
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $user = $this->staffUser($institution, ['issues.view', 'issues.edit']);
        $statusUpdatedAt = now()->subDay()->startOfSecond();
        $problem = $this->problem([
            'institution_id' => $institution->id,
            'status' => ProblemStatus::RESOLVED->value,
            'status_updated_at' => $statusUpdatedAt,
        ]);

        Sanctum::actingAs($user);
        $this->withoutMiddleware(\App\Http\Middleware\ExtendTokenExpiration::class);

        $this
            ->patchJson("/api/internal/problems/{$problem->id}/status", [
                'status' => ProblemStatus::RESOLVED->value,
            ])
            ->assertOk()
            ->assertJsonPath('status', ProblemStatus::RESOLVED->value);

        $this->assertSame(0, ProblemReviewHistory::where('problem_id', $problem->id)->count());
        $this->assertSame($statusUpdatedAt->toDateTimeString(), $problem->fresh()->status_updated_at->toDateTimeString());
    }

    public function test_bulk_status_update_logs_only_roots_that_changed_status(): void
    {
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $user = $this->staffUser($institution, ['issues.view', 'issues.edit']);
        $alreadyResolved = $this->problem([
            'institution_id' => $institution->id,
            'status' => ProblemStatus::RESOLVED->value,
        ]);
        $inReview = $this->problem([
            'institution_id' => $institution->id,
            'status' => ProblemStatus::IN_REVIEW->value,
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.bulkUpdateStatus'), [
                'problem_ids' => [$alreadyResolved->id, $inReview->id],
                'status' => ProblemStatus::RESOLVED->value,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(ProblemStatus::RESOLVED->value, $inReview->fresh()->status);
        $this->assertSame(0, ProblemReviewHistory::where('problem_id', $alreadyResolved->id)->count());
        $this->assertSame(1, ProblemReviewHistory::where('problem_id', $inReview->id)
            ->where('status', ProblemStatus::RESOLVED->value)
            ->where('status_changed', true)
            ->count());
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

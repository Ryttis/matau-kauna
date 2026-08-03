<?php

namespace Tests\Feature\Problems;

use App\Enums\ProblemReviewCommentVisibility;
use App\Enums\ProblemStatus;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\InstitutionDvsConfig;
use App\Models\InstitutionGisConfig;
use App\Models\InstitutionTeam;
use App\Models\Problem;
use App\Models\ProblemDvsTransfer;
use App\Models\ProblemGisTransfer;
use App\Models\ProblemReviewHistory;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use App\Services\DvsProblemTransferService;
use App\Services\ProblemReviewHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProblemReviewHistoryEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dvs_sync_without_actor_logs_system_status_change_entry(): void
    {
        $institution = Institution::create(['name' => 'DVS istaiga']);
        $problem = $this->problem([
            'institution_id' => $institution->id,
            'status' => ProblemStatus::IN_REVIEW->value,
        ]);
        $transfer = $this->dvsTransfer($institution, $problem);

        Http::fake([
            'https://example.test/api/token*' => Http::response(['access_token' => 'tkn', 'expires_in' => 3600]),
            'https://example.test/api/GaunamiDokumentai*' => Http::response([
                'atsakytaData' => '2026-07-01T10:00:00',
                'atsakymoPastabos' => 'Darbai atlikti.',
            ]),
        ]);

        app(DvsProblemTransferService::class)->syncFromDvs($transfer, ['trigger' => 'command']);

        $this->assertSame(ProblemStatus::RESOLVED->value, $problem->fresh()->status);

        $entry = ProblemReviewHistory::query()->where('problem_id', $problem->id)->sole();
        $this->assertSame(ProblemStatus::RESOLVED->value, $entry->status);
        $this->assertTrue((bool) $entry->status_changed);
        $this->assertSame(ProblemReviewCommentVisibility::Public->value, $entry->visibility);
        $this->assertNull($entry->user_id);
        $this->assertSame(ProblemReviewHistoryService::SYSTEM_RESPONSIBLE_DVS, $entry->responsible);
    }

    public function test_gis_pull_logs_status_change_entry_with_acting_user(): void
    {
        [$user, $problem] = $this->gisSetup();

        Http::fake([
            'https://example.test/FeatureServer/1/query' => Http::response([
                'features' => [['attributes' => ['busena' => 'Uždaryta']]],
            ]),
            'https://example.test/FeatureServer/1/queryAttachments' => Http::response([
                'attachmentGroups' => [],
            ]),
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.pullGis', $problem))
            ->assertSessionHasNoErrors();

        $this->assertSame(ProblemStatus::RESOLVED->value, $problem->fresh()->status);

        $entry = ProblemReviewHistory::query()->where('problem_id', $problem->id)->sole();
        $this->assertSame(ProblemStatus::RESOLVED->value, $entry->status);
        $this->assertTrue((bool) $entry->status_changed);
        $this->assertSame($user->id, $entry->user_id);
        $this->assertNotSame(ProblemReviewHistoryService::SYSTEM_RESPONSIBLE_GIS, $entry->responsible);
    }

    public function test_reassign_to_another_institution_logs_internal_entry_with_both_names(): void
    {
        $from = Institution::create(['name' => 'Senoji istaiga']);
        $to = Institution::create(['name' => 'Naujoji istaiga']);
        $user = $this->staffUser($from, UserRole::ROLE_INSTITUTION_ADMIN, [
            'issues.view', 'issues.edit', 'issues.reassign',
        ]);
        $problem = $this->problem(['institution_id' => $from->id]);

        $this
            ->actingAs($user)
            ->post(route('problems.reassignInstitution', $problem), ['institution_id' => $to->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertSame($to->id, $problem->fresh()->institution_id);

        $entry = ProblemReviewHistory::query()->where('problem_id', $problem->id)->sole();
        $this->assertSame(ProblemReviewCommentVisibility::Internal->value, $entry->visibility);
        $this->assertFalse((bool) $entry->status_changed);
        $this->assertStringContainsString('Senoji istaiga', $entry->comment);
        $this->assertStringContainsString('Naujoji istaiga', $entry->comment);
    }

    public function test_reassign_internal_entry_is_hidden_from_reporter(): void
    {
        $from = Institution::create(['name' => 'Senoji istaiga']);
        $to = Institution::create(['name' => 'Naujoji istaiga']);
        $user = $this->staffUser($from, UserRole::ROLE_INSTITUTION_ADMIN, [
            'issues.view', 'issues.edit', 'issues.reassign',
        ]);
        $reporter = $this->reporterUser();
        $problem = $this->problem([
            'institution_id' => $from->id,
            'reported_by_user_id' => $reporter->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.reassignInstitution', $problem), ['institution_id' => $to->id])
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($reporter)
            ->getJson(route('problems.reviewProgress', $problem))
            ->assertOk()
            ->assertJsonCount(0, 'reviewEntries');
    }

    public function test_assigning_employee_logs_internal_entry_with_assigned_status(): void
    {
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $user = $this->staffUser($institution, UserRole::ROLE_INSTITUTION_ADMIN, [
            'issues.view', 'issues.edit', 'issues.assign',
        ]);
        $employeeUser = $this->staffUser($institution, UserRole::ROLE_EMPLOYEE, ['issues.view']);
        $employee = Employee::create([
            'institution_id' => $institution->id,
            'name' => 'Jonas',
            'surname' => 'Jonaitis',
            'email' => $employeeUser->email,
        ]);
        $problem = $this->problem(['institution_id' => $institution->id]);

        $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.store', $problem), [
                'employee_id' => $employee->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProblemStatus::ASSIGNED->value, $problem->fresh()->status);

        $entry = ProblemReviewHistory::query()->where('problem_id', $problem->id)->sole();
        $this->assertSame(ProblemStatus::ASSIGNED->value, $entry->status);
        $this->assertTrue((bool) $entry->status_changed);
        $this->assertSame(ProblemReviewCommentVisibility::Internal->value, $entry->visibility);
        $this->assertStringContainsString('Jonas Jonaitis', $entry->comment);
    }

    public function test_assigning_team_logs_internal_entry_with_team_name(): void
    {
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $user = $this->staffUser($institution, UserRole::ROLE_INSTITUTION_ADMIN, [
            'issues.view', 'issues.edit', 'issues.assign',
        ]);
        $employeeUser = $this->staffUser($institution, UserRole::ROLE_EMPLOYEE, ['issues.view']);
        $employee = Employee::create([
            'institution_id' => $institution->id,
            'name' => 'Jonas',
            'surname' => 'Jonaitis',
            'email' => $employeeUser->email,
        ]);
        $team = InstitutionTeam::create([
            'institution_id' => $institution->id,
            'name' => 'Kelių brigada',
        ]);
        $team->employees()->attach($employee->id);
        $problem = $this->problem(['institution_id' => $institution->id]);

        $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.store', $problem), [
                'institution_team_id' => $team->id,
            ])
            ->assertSessionHasNoErrors();

        $entry = ProblemReviewHistory::query()->where('problem_id', $problem->id)->sole();
        $this->assertSame(ProblemStatus::ASSIGNED->value, $entry->status);
        $this->assertStringContainsString('Kelių brigada', $entry->comment);
    }

    public function test_admin_assign_logs_institution_and_status_entries(): void
    {
        $institution = Institution::create(['name' => 'Naujoji istaiga']);
        $user = $this->staffUser(null, UserRole::ROLE_SYSTEM_ADMIN, [
            'issues.view', 'issues.edit', 'issues.publish',
        ]);
        $problem = $this->problem(['status' => ProblemStatus::UNREGISTERED->value]);

        $this
            ->actingAs($user)
            ->post(route('problems.assign.store'), [
                'problem_ids' => [$problem->id],
                'institution_id' => $institution->id,
                'status' => ProblemStatus::NEW->value,
            ])
            ->assertSessionHasNoErrors();

        $entries = ProblemReviewHistory::query()->where('problem_id', $problem->id)->get();
        $this->assertCount(2, $entries);

        $institutionEntry = $entries->firstWhere('visibility', ProblemReviewCommentVisibility::Internal->value);
        $this->assertNotNull($institutionEntry);
        $this->assertStringContainsString('Naujoji istaiga', $institutionEntry->comment);

        $statusEntry = $entries->firstWhere('visibility', ProblemReviewCommentVisibility::Public->value);
        $this->assertNotNull($statusEntry);
        $this->assertSame(ProblemStatus::NEW->value, $statusEntry->status);
        $this->assertTrue((bool) $statusEntry->status_changed);
    }

    public function test_staff_review_progress_of_merged_child_shows_root_entries(): void
    {
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $user = $this->staffUser($institution, UserRole::ROLE_INSTITUTION_ADMIN, ['issues.view', 'issues.edit']);
        $root = $this->problem(['institution_id' => $institution->id]);
        $child = $this->problem([
            'institution_id' => $institution->id,
            'merged_into_problem_id' => $root->id,
        ]);

        app(ProblemReviewHistoryService::class)
            ->logStatusChangeEntry($child, $user, ProblemStatus::RESOLVED->value);

        $this->assertSame(1, ProblemReviewHistory::where('problem_id', $root->id)->count());
        $this->assertSame(0, ProblemReviewHistory::where('problem_id', $child->id)->count());

        $this
            ->actingAs($user)
            ->getJson(route('problems.reviewProgress', $child))
            ->assertOk()
            ->assertJsonCount(1, 'reviewEntries')
            ->assertJsonPath('reviewEntries.0.status', ProblemStatus::RESOLVED->value);
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

    private function staffUser(?Institution $institution, string $role, array $permissions): User
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

    private function reporterUser(): User
    {
        $user = User::factory()->create([
            'terms_accepted_at' => now(),
        ]);
        $role = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_REPORTER],
            ['name' => UserRole::ROLE_REPORTER],
        );
        $role->users()->attach($user->id);

        return $user->fresh();
    }

    private function dvsTransfer(Institution $institution, Problem $problem): ProblemDvsTransfer
    {
        $config = InstitutionDvsConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'complaint_api' => InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI,
            'gaunami_dokumentai_url' => 'https://example.test/api/GaunamiDokumentai',
            'token_url' => 'https://example.test/api/token',
            'username' => 'dvs-user',
            'password' => 'secret',
            'client_id' => 'client',
            'client_secret' => 'client-secret',
            'rusis_kodas' => 'RUSIS',
            'gaunami_nukreipto_id' => '123',
            'gaunami_sudarytojas_id' => '456',
            'gaunami_registro_kodas' => 'REG',
            'active_token' => 'cached-token',
            'active_token_expires_at' => now()->addHour(),
        ]);

        return ProblemDvsTransfer::create([
            'problem_id' => $problem->id,
            'institution_dvs_config_id' => $config->id,
            'complaint_api' => InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI,
            'external_object_id' => '999',
            'send_status' => ProblemDvsTransfer::STATUS_SENT,
        ]);
    }

    /**
     * @return array{0:User,1:Problem}
     */
    private function gisSetup(): array
    {
        $institution = Institution::create(['name' => 'GIS istaiga']);
        $user = $this->staffUser($institution, UserRole::ROLE_SYSTEM_ADMIN, [
            'issues.view', 'issues.edit', 'invitations.manage',
        ]);
        $problem = $this->problem([
            'institution_id' => $institution->id,
            'status' => ProblemStatus::IN_REVIEW->value,
        ]);

        $config = InstitutionGisConfig::create([
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
            'active_token' => 'cached-token',
            'active_token_expires_at' => now()->addHour(),
        ]);

        ProblemGisTransfer::create([
            'problem_id' => $problem->id,
            'institution_gis_config_id' => $config->id,
            'external_object_id' => '321',
            'external_global_id' => 'ABC12345-DEAD-BEEF-CAFE-000000000001',
            'send_status' => ProblemGisTransfer::STATUS_SENT,
        ]);

        return [$user, $problem];
    }
}

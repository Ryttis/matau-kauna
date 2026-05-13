<?php

namespace Tests\Feature\Problems;

use App\Models\Institution;
use App\Models\InstitutionGisConfig;
use App\Models\Problem;
use App\Models\ProblemAttachment;
use App\Models\ProblemGisTransfer;
use App\Models\ProblemGisTransferAttachment;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProblemGisPullAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $problemDir = public_path('files/problems');
        if (is_dir($problemDir)) {
            File::deleteDirectory($problemDir);
        }

        parent::tearDown();
    }

    public function test_pull_attachments_creates_new_problem_attachment_marked_as_gis(): void
    {
        [$user, $problem, $transfer] = $this->setupTransferReadyForPull();

        Http::fake([
            'https://example.test/FeatureServer/1/query' => Http::response([
                'features' => [['attributes' => ['busena' => 'Nagrinėjama']]],
            ]),
            'https://example.test/FeatureServer/1/queryAttachments' => Http::response([
                'attachmentGroups' => [[
                    'parentGlobalId' => '{'.$transfer->external_global_id.'}',
                    'attachmentInfos' => [[
                        'id' => 42,
                        'globalId' => '{ATT-GUID-001}',
                        'name' => 'after-fix.jpg',
                        'contentType' => 'image/jpeg',
                        'size' => 8,
                    ]],
                ]],
            ]),
            'https://example.test/FeatureServer/1/'.$transfer->external_object_id.'/attachments/42*' => Http::response(
                'FAKEDATA',
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.pullGis', $problem));

        $response->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('problem_attachments', [
            'problem_id' => $problem->id,
            'original_name' => 'after-fix.jpg',
            'source' => 'gis',
        ]);
        $this->assertDatabaseHas('problem_gis_transfer_attachments', [
            'problem_gis_transfer_id' => $transfer->id,
            'external_attachment_id' => '42',
            'direction' => ProblemGisTransferAttachment::DIRECTION_INBOUND,
            'status' => ProblemGisTransferAttachment::STATUS_SENT,
        ]);
    }

    public function test_pull_attachments_is_idempotent(): void
    {
        [$user, $problem, $transfer] = $this->setupTransferReadyForPull();

        Http::fake([
            'https://example.test/FeatureServer/1/query' => Http::response([
                'features' => [['attributes' => []]],
            ]),
            'https://example.test/FeatureServer/1/queryAttachments' => Http::response([
                'attachmentGroups' => [[
                    'attachmentInfos' => [[
                        'id' => 42,
                        'globalId' => '{ATT-GUID-001}',
                        'name' => 'after-fix.jpg',
                        'contentType' => 'image/jpeg',
                        'size' => 8,
                    ]],
                ]],
            ]),
            'https://example.test/FeatureServer/1/'.$transfer->external_object_id.'/attachments/42*' => Http::response(
                'FAKEDATA',
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $this->actingAs($user)->post(route('problems.assignInstitution.pullGis', $problem));
        $this->actingAs($user)->post(route('problems.assignInstitution.pullGis', $problem));

        $this->assertSame(1, ProblemAttachment::where('problem_id', $problem->id)->count());
        $this->assertSame(1, ProblemGisTransferAttachment::where('problem_gis_transfer_id', $transfer->id)->count());
    }

    public function test_pull_syncs_status_from_gis(): void
    {
        [$user, $problem, $transfer] = $this->setupTransferReadyForPull();

        Http::fake([
            'https://example.test/FeatureServer/1/query' => Http::response([
                'features' => [['attributes' => ['busena' => 'Išspręsta']]],
            ]),
            'https://example.test/FeatureServer/1/queryAttachments' => Http::response([
                'attachmentGroups' => [],
            ]),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.pullGis', $problem));

        $response->assertSessionHasNoErrors()->assertRedirect();
        $this->assertStringContainsString('būsena pakeista', session('success'));
        $this->assertSame('isspresta', $problem->fresh()->status);

        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/FeatureServer/1/query'
            && $request['outFields'] === 'busena,vykdymo_terminas,pranesimo_tipas,adresas,pranese');
    }

    public function test_pull_accepts_arcgis_alias_registruojama_as_nauja(): void
    {
        [$user, $problem, $transfer] = $this->setupTransferReadyForPull();

        // Pre-flip the problem so we can assert it gets reset to "nauja"
        $problem->update(['status' => 'nagrinejama']);

        Http::fake([
            'https://example.test/FeatureServer/1/query' => Http::response([
                'features' => [['attributes' => ['busena' => 'Registruojama']]],
            ]),
            'https://example.test/FeatureServer/1/queryAttachments' => Http::response([
                'attachmentGroups' => [],
            ]),
        ]);

        $this->actingAs($user)->post(route('problems.assignInstitution.pullGis', $problem));

        $this->assertSame('nauja', $problem->fresh()->status);
    }

    public function test_pull_syncs_status_and_deadline_from_mapped_gis_fields(): void
    {
        [$user, $problem, $transfer] = $this->setupTransferReadyForPull();
        $deadline = now()->addDays(5)->startOfDay();

        InstitutionGisConfig::findOrFail($transfer->institution_gis_config_id)->forceFill([
            'use_field_mapping' => true,
            'field_mapping' => [
                'status' => 'gis_status',
                'category' => 'gis_type',
                'created_at' => 'gis_created',
                'completion_deadline' => 'gis_deadline',
                'address' => 'gis_address',
                'reporter' => 'gis_reporter',
            ],
        ])->save();

        Http::fake([
            'https://example.test/FeatureServer/1/query' => Http::response([
                'features' => [[
                    'attributes' => [
                        'gis_status' => 'isspresta',
                        'gis_deadline' => $deadline->getTimestampMs(),
                    ],
                ]],
            ]),
            'https://example.test/FeatureServer/1/queryAttachments' => Http::response([
                'attachmentGroups' => [],
            ]),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.pullGis', $problem));

        $response->assertSessionHasNoErrors()->assertRedirect();

        $fresh = $problem->fresh();
        $this->assertSame('isspresta', $fresh->status);
        $this->assertTrue($deadline->equalTo($fresh->completion_deadline->startOfDay()));

        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/FeatureServer/1/query'
            && $request['outFields'] === 'gis_status,gis_deadline,gis_type,gis_created,gis_address,gis_reporter');
    }

    public function test_pull_resolves_mapped_gis_status_value_to_local_status(): void
    {
        [$user, $problem, $transfer] = $this->setupTransferReadyForPull();
        $problem->update(['status' => 'nagrinejama']);

        InstitutionGisConfig::findOrFail($transfer->institution_gis_config_id)->forceFill([
            'use_field_mapping' => true,
            'field_mapping' => [
                'status' => 'gis_status',
                'category' => 'gis_type',
                'created_at' => 'gis_created',
                'completion_deadline' => 'gis_deadline',
                'address' => 'gis_address',
                'reporter' => 'gis_reporter',
                'status_value_mapping' => [
                    'isspresta' => [
                        'code' => 'Atlikta',
                        'name' => 'Atlikta',
                    ],
                ],
            ],
        ])->save();

        Http::fake([
            'https://example.test/FeatureServer/1/query' => Http::response([
                'features' => [[
                    'attributes' => [
                        'gis_status' => 'Atlikta',
                    ],
                ]],
            ]),
            'https://example.test/FeatureServer/1/queryAttachments' => Http::response([
                'attachmentGroups' => [],
            ]),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.pullGis', $problem));

        $response->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('isspresta', $problem->fresh()->status);
    }

    /**
     * @return array{0:User,1:Problem,2:ProblemGisTransfer}
     */
    private function setupTransferReadyForPull(): array
    {
        $institution = Institution::create(['name' => 'GIS Institution']);

        $user = User::factory()->create();
        $role = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_SYSTEM_ADMIN],
            ['name' => 'System administrator'],
        );
        $role->users()->attach($user->id);
        foreach (['invitations.manage', 'issues.view'] as $key) {
            $permission = UserRolePermission::firstOrCreate(
                ['permission' => $key],
                ['name' => $key],
            );
            if (! $role->permissions()->where('permission_id', $permission->id)->exists()) {
                $role->permissions()->attach($permission->id);
            }
        }
        $user = $user->fresh();

        $problem = Problem::create([
            'status' => 'nauja',
            'category' => 'Neveikia šviestuvas',
            'address' => 'Laisvės al. 15, Kaunas',
            'description' => 'Neveikia šviestuvas prie sankryžos.',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'institution_id' => $institution->id,
        ]);

        $config = InstitutionGisConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
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

        $transfer = ProblemGisTransfer::create([
            'problem_id' => $problem->id,
            'institution_gis_config_id' => $config->id,
            'external_object_id' => '321',
            'external_global_id' => 'ABC12345-DEAD-BEEF-CAFE-000000000001',
            'send_status' => ProblemGisTransfer::STATUS_SENT,
            'sent_at' => now(),
        ]);

        return [$user, $problem, $transfer];
    }
}

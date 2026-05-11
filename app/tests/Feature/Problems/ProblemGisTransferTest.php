<?php

namespace Tests\Feature\Problems;

use App\Models\ClassifierValue;
use App\Models\Institution;
use App\Models\InstitutionGisConfig;
use App\Models\Problem;
use App\Models\ProblemAttachment;
use App\Models\ProblemGisTransfer;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProblemGisTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('testing-gis'));

        parent::tearDown();
    }

    public function test_problem_can_be_sent_to_gis_with_existing_token_and_attachments(): void
    {
        File::ensureDirectoryExists(public_path('testing-gis'));
        File::put(public_path('testing-gis/problem-note.txt'), 'test attachment');

        $institution = Institution::create([
            'name' => 'GIS Institution',
        ]);
        $user = $this->createSystemAdmin();
        $problem = Problem::create([
            'status' => 'nauja',
            'category' => 'Neveikia šviestuvas',
            'address' => 'Laisvės al. 15, Kaunas',
            'description' => 'Neveikia šviestuvas prie sankryžos.',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'institution_id' => $institution->id,
            'reported_by_user_id' => $user->id,
        ]);
        ProblemAttachment::create([
            'problem_id' => $problem->id,
            'path' => 'testing-gis/problem-note.txt',
            'original_name' => 'problem-note.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 15,
            'kind' => 'photo',
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
            'active_token' => 'cached-token',
            'active_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://example.test/FeatureServer/1/applyEdits' => Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 321, 'globalId' => '{ABC12345-DEAD-BEEF-CAFE-000000000001}'],
                ],
            ]),
            'https://example.test/FeatureServer/1/321/addAttachment' => Http::response([
                'addAttachmentResult' => [
                    'success' => true,
                    'objectId' => 654,
                    'globalId' => 'ATT-GLOBAL-111',
                ],
            ]),
            'https://digital.kaunas.lt/portal/sharing/rest/generateToken' => Http::response([
                'token' => 'should-not-be-used',
                'expires' => now()->addHour()->getTimestampMs(),
            ]),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.sendGis', $problem));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $transfer = $problem->fresh()->gisTransfer()->with('attachmentTransfers')->first();

        $this->assertNotNull($transfer);
        $this->assertSame('321', $transfer->external_object_id);
        $this->assertSame('ABC12345-DEAD-BEEF-CAFE-000000000001', $transfer->external_global_id);
        $this->assertSame(ProblemGisTransfer::STATUS_SENT, $transfer->send_status);
        $this->assertCount(1, $transfer->attachmentTransfers);
        $this->assertSame('sent', $transfer->attachmentTransfers->first()->status);
        $this->assertSame('outbound', $transfer->attachmentTransfers->first()->direction);
        $this->assertSame('ATT-GLOBAL-111', $transfer->attachmentTransfers->first()->external_global_id);
        $this->assertSame($config->id, $transfer->institution_gis_config_id);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/FeatureServer/1/applyEdits'
            && $request['token'] === 'cached-token'
            && ! isset($request['useGlobalIds'])
            && (int) $request['inSR'] === 4326
            && $this->requestAttributeKeys($request) === [
                'busena',
                'pranesimo_tipas',
                'registravimo_data',
                'vykdymo_terminas',
                'adresas',
                'pranese',
            ]);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://digital.kaunas.lt/portal/sharing/rest/generateToken');
    }

    public function test_problem_can_be_sent_to_gis_with_custom_field_mapping(): void
    {
        $institution = Institution::create([
            'name' => 'GIS Institution',
        ]);
        $user = $this->createSystemAdmin();
        $problem = Problem::create([
            'status' => 'nauja',
            'category' => 'Neveikia Å¡viestuvas',
            'address' => 'LaisvÄ—s al. 15, Kaunas',
            'description' => 'Neveikia Å¡viestuvas prie sankryÅ¾os.',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'institution_id' => $institution->id,
            'reported_by_user_id' => $user->id,
        ]);
        InstitutionGisConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
            'technical_username' => 'arcgis-user',
            'technical_password' => 'secret',
            'client_type' => 'referer',
            'token_expiration_minutes' => 60,
            'feature_server_url' => 'https://example.test/FeatureServer/1',
            'attachment_url' => 'https://example.test/FeatureServer/1',
            'use_field_mapping' => true,
            'field_mapping' => [
                'status' => 'gis_status',
                'category' => 'gis_type',
                'created_at' => 'gis_created_at',
                'completion_deadline' => 'gis_deadline',
                'address' => 'gis_address',
                'reporter' => 'gis_reporter',
                'internal_number' => 'gis_internal_number',
                'description' => 'gis_description',
            ],
            'active_token' => 'cached-token',
            'active_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://example.test/FeatureServer/1/applyEdits' => Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 987],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.sendGis', $problem));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/FeatureServer/1/applyEdits'
            && $request['token'] === 'cached-token'
            && $this->requestAttributeKeys($request) === [
                'gis_status',
                'gis_type',
                'gis_created_at',
                'gis_deadline',
                'gis_address',
                'gis_reporter',
                'gis_internal_number',
                'gis_description',
            ]
            && ($this->requestAttributes($request)['gis_internal_number'] ?? null) === $problem->internal_number
            && ($this->requestAttributes($request)['gis_description'] ?? null) === $problem->description);
    }

    public function test_category_value_mapping_prefers_problem_nature_for_gis_payload(): void
    {
        $institution = Institution::create([
            'name' => 'GIS Institution',
        ]);
        $user = $this->createSystemAdmin();
        $type = ClassifierValue::create([
            'name' => 'Apšvietimas',
            'is_active' => true,
        ]);
        $nature = ClassifierValue::create([
            'parent_id' => $type->id,
            'name' => 'Neveikianti lempa',
            'is_active' => true,
        ]);
        $problem = Problem::create([
            'status' => 'nauja',
            'category' => 'Apšvietimas',
            'problem_type_id' => $type->id,
            'problem_nature_id' => $nature->id,
            'address' => 'Laisvės al. 15, Kaunas',
            'description' => 'Neveikianti lempa.',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'institution_id' => $institution->id,
        ]);
        InstitutionGisConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
            'technical_username' => 'arcgis-user',
            'technical_password' => 'secret',
            'client_type' => 'referer',
            'token_expiration_minutes' => 60,
            'feature_server_url' => 'https://example.test/FeatureServer/1',
            'attachment_url' => 'https://example.test/FeatureServer/1',
            'use_field_mapping' => true,
            'field_mapping' => [
                'category' => 'pranesimo_tipas',
                'category_value_mapping' => [
                    (string) $type->id => [
                        'code' => 'Kita',
                        'name' => 'Kita',
                    ],
                    (string) $nature->id => [
                        'code' => 'Neveikia šviestuvas',
                        'name' => 'Neveikia šviestuvas',
                    ],
                ],
            ],
            'active_token' => 'cached-token',
            'active_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://example.test/FeatureServer/1/applyEdits' => Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 101],
                ],
            ]),
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.sendGis', $problem))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/FeatureServer/1/applyEdits'
            && ($this->requestAttributes($request)['pranesimo_tipas'] ?? null) === 'Neveikia šviestuvas');
    }

    public function test_category_value_mapping_falls_back_to_problem_type_without_nature(): void
    {
        $institution = Institution::create([
            'name' => 'GIS Institution',
        ]);
        $user = $this->createSystemAdmin();
        $type = ClassifierValue::create([
            'name' => 'Apšvietimas',
            'is_active' => true,
        ]);
        $problem = Problem::create([
            'status' => 'nauja',
            'category' => 'Apšvietimas',
            'problem_type_id' => $type->id,
            'problem_nature_id' => null,
            'address' => 'Laisvės al. 15, Kaunas',
            'description' => 'Trūksta apšvietimo.',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'institution_id' => $institution->id,
        ]);
        InstitutionGisConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
            'technical_username' => 'arcgis-user',
            'technical_password' => 'secret',
            'client_type' => 'referer',
            'token_expiration_minutes' => 60,
            'feature_server_url' => 'https://example.test/FeatureServer/1',
            'attachment_url' => 'https://example.test/FeatureServer/1',
            'use_field_mapping' => true,
            'field_mapping' => [
                'category' => 'pranesimo_tipas',
                'category_value_mapping' => [
                    (string) $type->id => [
                        'code' => 'Kita',
                        'name' => 'Kita',
                    ],
                ],
            ],
            'active_token' => 'cached-token',
            'active_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://example.test/FeatureServer/1/applyEdits' => Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 102],
                ],
            ]),
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.sendGis', $problem))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/FeatureServer/1/applyEdits'
            && ($this->requestAttributes($request)['pranesimo_tipas'] ?? null) === 'Kita');
    }

    public function test_unmapped_category_value_preserves_problem_category(): void
    {
        $institution = Institution::create([
            'name' => 'GIS Institution',
        ]);
        $user = $this->createSystemAdmin();
        $type = ClassifierValue::create([
            'name' => 'Apšvietimas',
            'is_active' => true,
        ]);
        $problem = Problem::create([
            'status' => 'nauja',
            'category' => 'Apšvietimas',
            'problem_type_id' => $type->id,
            'address' => 'Laisvės al. 15, Kaunas',
            'description' => 'Kitas pranešimas.',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'institution_id' => $institution->id,
        ]);
        InstitutionGisConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
            'technical_username' => 'arcgis-user',
            'technical_password' => 'secret',
            'client_type' => 'referer',
            'token_expiration_minutes' => 60,
            'feature_server_url' => 'https://example.test/FeatureServer/1',
            'attachment_url' => 'https://example.test/FeatureServer/1',
            'use_field_mapping' => true,
            'field_mapping' => [
                'category' => 'pranesimo_tipas',
                'category_value_mapping' => [],
            ],
            'active_token' => 'cached-token',
            'active_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://example.test/FeatureServer/1/applyEdits' => Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 103],
                ],
            ]),
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.sendGis', $problem))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/FeatureServer/1/applyEdits'
            && ($this->requestAttributes($request)['pranesimo_tipas'] ?? null) === 'Apšvietimas');
    }

    public function test_reporter_mapping_can_send_resident_label(): void
    {
        $institution = Institution::create([
            'name' => 'GIS Institution',
        ]);
        $user = $this->createSystemAdmin();
        $reporter = User::factory()->create([
            'name' => 'Jonas',
            'surname' => 'Jonaitis',
        ]);
        $problem = Problem::create([
            'status' => 'nauja',
            'category' => 'Apšvietimas',
            'address' => 'Laisvės al. 15, Kaunas',
            'description' => 'Neveikia lempa.',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'institution_id' => $institution->id,
            'reported_by_user_id' => $reporter->id,
        ]);
        InstitutionGisConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
            'technical_username' => 'arcgis-user',
            'technical_password' => 'secret',
            'client_type' => 'referer',
            'token_expiration_minutes' => 60,
            'feature_server_url' => 'https://example.test/FeatureServer/1',
            'attachment_url' => 'https://example.test/FeatureServer/1',
            'use_field_mapping' => true,
            'field_mapping' => [
                'reporter' => 'pranese',
                'reporter_value_mode' => 'resident',
            ],
            'active_token' => 'cached-token',
            'active_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://example.test/FeatureServer/1/applyEdits' => Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 104],
                ],
            ]),
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.sendGis', $problem))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/FeatureServer/1/applyEdits'
            && ($this->requestAttributes($request)['pranese'] ?? null) === 'Gyventojas');
    }

    public function test_reporter_mapping_can_send_custom_admin_text(): void
    {
        $institution = Institution::create([
            'name' => 'GIS Institution',
        ]);
        $user = $this->createSystemAdmin();
        $problem = Problem::create([
            'status' => 'nauja',
            'category' => 'Apšvietimas',
            'address' => 'Laisvės al. 15, Kaunas',
            'description' => 'Neveikia lempa.',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'institution_id' => $institution->id,
        ]);
        InstitutionGisConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
            'technical_username' => 'arcgis-user',
            'technical_password' => 'secret',
            'client_type' => 'referer',
            'token_expiration_minutes' => 60,
            'feature_server_url' => 'https://example.test/FeatureServer/1',
            'attachment_url' => 'https://example.test/FeatureServer/1',
            'use_field_mapping' => true,
            'field_mapping' => [
                'reporter' => 'pranese',
                'reporter_value_mode' => 'custom',
                'reporter_default_value' => 'Administratoriaus tekstas',
            ],
            'active_token' => 'cached-token',
            'active_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://example.test/FeatureServer/1/applyEdits' => Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 105],
                ],
            ]),
        ]);

        $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.sendGis', $problem))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/FeatureServer/1/applyEdits'
            && ($this->requestAttributes($request)['pranese'] ?? null) === 'Administratoriaus tekstas');
    }

    public function test_problem_send_refreshes_token_after_arcgis_498_error(): void
    {
        $institution = Institution::create([
            'name' => 'GIS Institution',
        ]);
        $user = $this->createSystemAdmin();
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
            'active_token' => 'old-token',
            'active_token_expires_at' => now()->addHour(),
        ]);

        $applyEditsCalls = 0;
        Http::fake(function ($request) use (&$applyEditsCalls) {
            if ($request->url() === 'https://example.test/FeatureServer/1/applyEdits') {
                $applyEditsCalls++;

                if ($applyEditsCalls === 1) {
                    return Http::response([
                        'error' => [
                            'code' => 498,
                            'message' => 'Invalid token',
                        ],
                    ]);
                }

                return Http::response([
                    'addResults' => [
                        ['success' => true, 'objectId' => 777],
                    ],
                ]);
            }

            if ($request->url() === 'https://digital.kaunas.lt/portal/sharing/rest/generateToken') {
                return Http::response([
                    'token' => 'new-token',
                    'expires' => now()->addHour()->getTimestampMs(),
                ]);
            }

            return Http::response([], 500);
        });

        $response = $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.sendGis', $problem));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $transfer = $problem->fresh()->gisTransfer;

        $this->assertSame('777', $transfer->external_object_id);
        $this->assertSame(ProblemGisTransfer::STATUS_SENT, $transfer->send_status);
        $this->assertSame('new-token', $config->fresh()->active_token);

        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/FeatureServer/1/applyEdits'
            && $request['token'] === 'old-token');
        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/FeatureServer/1/applyEdits'
            && $request['token'] === 'new-token');
    }

    public function test_attachment_failures_keep_external_object_reference_for_retry(): void
    {
        File::ensureDirectoryExists(public_path('testing-gis'));
        File::put(public_path('testing-gis/problem-note.txt'), 'test attachment');

        $institution = Institution::create([
            'name' => 'GIS Institution',
        ]);
        $user = $this->createSystemAdmin();
        $problem = Problem::create([
            'status' => 'nauja',
            'category' => 'Neveikia šviestuvas',
            'address' => 'Laisvės al. 15, Kaunas',
            'description' => 'Neveikia šviestuvas prie sankryžos.',
            'lat' => 54.8985,
            'lng' => 23.9036,
            'institution_id' => $institution->id,
        ]);
        ProblemAttachment::create([
            'problem_id' => $problem->id,
            'path' => 'testing-gis/problem-note.txt',
            'original_name' => 'problem-note.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 15,
            'kind' => 'photo',
        ]);
        InstitutionGisConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
            'technical_username' => 'arcgis-user',
            'technical_password' => 'secret',
            'client_type' => 'referer',
            'token_expiration_minutes' => 60,
            'feature_server_url' => 'https://example.test/FeatureServer/1',
            'attachment_url' => 'https://example.test/FeatureServer/1',
            'active_token' => 'cached-token',
            'active_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://example.test/FeatureServer/1/applyEdits' => Http::response([
                'addResults' => [
                    ['success' => true, 'objectId' => 555],
                ],
            ]),
            'https://example.test/FeatureServer/1/555/addAttachment' => Http::response([
                'addAttachmentResult' => [
                    'success' => false,
                ],
            ]),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('problems.assignInstitution.sendGis', $problem));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $transfer = $problem->fresh()->gisTransfer()->with('attachmentTransfers')->first();

        $this->assertSame('555', $transfer->external_object_id);
        $this->assertSame(ProblemGisTransfer::STATUS_ATTACHMENT_FAILED, $transfer->send_status);
        $this->assertNotNull($transfer->last_error);
        $this->assertSame('failed', $transfer->attachmentTransfers->first()->status);
    }

    private function createSystemAdmin(): User
    {
        $user = User::factory()->create();
        $role = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_SYSTEM_ADMIN],
            ['name' => 'System administrator'],
        );
        $role->users()->attach($user->id);

        $permissionKeys = ['invitations.manage', 'issues.view', 'issues.assign'];
        foreach ($permissionKeys as $key) {
            $permission = UserRolePermission::firstOrCreate(
                ['permission' => $key],
                ['name' => $key],
            );
            if (! $role->permissions()->where('permission_id', $permission->id)->exists()) {
                $role->permissions()->attach($permission->id);
            }
        }

        return $user->fresh();
    }

    private function requestAttributes($request): array
    {
        $adds = json_decode((string) $request['adds'], true);

        return $adds[0]['attributes'] ?? [];
    }

    private function requestAttributeKeys($request): array
    {
        return array_keys($this->requestAttributes($request));
    }
}

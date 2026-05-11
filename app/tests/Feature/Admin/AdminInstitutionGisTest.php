<?php

namespace Tests\Feature\Admin;

use App\Models\ClassifierValue;
use App\Models\Institution;
use App\Models\InstitutionGisConfig;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminInstitutionGisTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_institution_with_gis_enabled(): void
    {
        $admin = $this->createSystemAdmin();

        $response = $this
            ->actingAs($admin)
            ->postJson('/admin/institutions', [
                'name' => 'Kauno m. savivaldybė',
                'code' => 'KMS',
                'type' => 'savivaldybė',
                'contact_phone' => '+370 37 409 000',
                'contact_email' => 'info@kaunas.lt',
                'contact_address' => 'Laisvės al. 96, Kaunas',
                'integration_email' => 'problemos@kaunas.lt',
                'integration_dvs' => null,
                'is_active' => true,
                'is_email_only' => false,
                'gis' => [
                    'enabled' => true,
                    'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
                    'technical_username' => 'arcgis-user',
                    'technical_password' => 'arcgis-secret',
                    'client_type' => 'referer',
                    'token_expiration_minutes' => 60,
                    'feature_server_url' => 'https://example.test/FeatureServer/0',
                    'attachment_url' => 'https://example.test/FeatureServer/0',
                    'wkid' => 3346,
                ],
            ]);

        $response->assertSuccessful();

        $institution = Institution::where('code', 'KMS')->firstOrFail();

        $this->assertDatabaseHas('institution_gis_configs', [
            'institution_id' => $institution->id,
            'enabled' => true,
            'wkid' => 3346,
            'technical_username' => 'arcgis-user',
        ]);

        $config = InstitutionGisConfig::where('institution_id', $institution->id)->firstOrFail();
        $this->assertSame('arcgis-secret', $config->technical_password);
    }

    public function test_admin_edits_institution_and_blank_password_preserves_existing(): void
    {
        $admin = $this->createSystemAdmin();

        $institution = Institution::create([
            'name' => 'Test',
            'contact_email' => 'test@example.com',
        ]);
        $config = InstitutionGisConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
            'technical_username' => 'u',
            'technical_password' => 'original-password',
            'client_type' => 'referer',
            'token_expiration_minutes' => 60,
            'feature_server_url' => 'https://example.test/FeatureServer/0',
            'attachment_url' => 'https://example.test/FeatureServer/0',
            'wkid' => 3346,
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson('/admin/institutions/'.$institution->id, [
                'name' => 'Test',
                'contact_email' => 'test@example.com',
                'is_active' => true,
                'is_email_only' => false,
                'gis' => [
                    'enabled' => true,
                    'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
                    'technical_username' => 'u',
                    'technical_password' => '',
                    'client_type' => 'referer',
                    'token_expiration_minutes' => 120,
                    'feature_server_url' => 'https://example.test/FeatureServer/0',
                    'attachment_url' => 'https://example.test/FeatureServer/0',
                    'wkid' => 4326,
                ],
            ]);

        $response->assertSuccessful();
        $config->refresh();

        $this->assertSame('original-password', $config->technical_password);
        $this->assertSame(120, $config->token_expiration_minutes);
        $this->assertSame(4326, $config->wkid);
        $this->assertFalse($config->use_field_mapping);
        $this->assertNull($config->field_mapping);
    }

    public function test_admin_edits_institution_with_gis_field_mapping(): void
    {
        $admin = $this->createSystemAdmin();
        $type = ClassifierValue::create([
            'name' => 'Apšvietimas',
            'is_active' => true,
        ]);
        $nature = ClassifierValue::create([
            'parent_id' => $type->id,
            'name' => 'Neveikianti lempa',
            'is_active' => true,
        ]);

        $institution = Institution::create([
            'name' => 'Test',
            'contact_email' => 'test@example.com',
        ]);
        InstitutionGisConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
            'technical_username' => 'u',
            'technical_password' => 'original-password',
            'client_type' => 'referer',
            'token_expiration_minutes' => 60,
            'feature_server_url' => 'https://example.test/FeatureServer/0',
            'attachment_url' => 'https://example.test/FeatureServer/0',
            'wkid' => 3346,
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson('/admin/institutions/'.$institution->id, [
                'name' => 'Test',
                'contact_email' => 'test@example.com',
                'is_active' => true,
                'is_email_only' => false,
                'gis' => [
                    'enabled' => true,
                    'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
                    'technical_username' => 'u',
                    'technical_password' => '',
                    'client_type' => 'referer',
                    'token_expiration_minutes' => 60,
                    'feature_server_url' => 'https://example.test/FeatureServer/0',
                    'attachment_url' => 'https://example.test/FeatureServer/0',
                    'metadata_url' => 'https://example.test/FeatureServer/queryDomains',
                    'metadata_layer_id' => '0',
                    'wkid' => 3346,
                    'use_field_mapping' => true,
                    'field_mapping' => [
                        'status' => 'gis_status',
                        'category' => 'gis_type',
                        'created_at' => 'gis_created',
                        'completion_deadline' => 'gis_deadline',
                        'address' => 'gis_address',
                        'reporter' => 'gis_reporter',
                        'reporter_value_mode' => 'custom',
                        'reporter_default_value' => 'Dispečeris',
                        'internal_number' => 'gis_number',
                        'description' => 'gis_description',
                        'category_value_mapping' => [
                            (string) $nature->id => [
                                'code' => 'Neveikia šviestuvas',
                                'name' => 'Neveikia šviestuvas',
                            ],
                        ],
                    ],
                ],
            ]);

        $response->assertSuccessful();

        $config = InstitutionGisConfig::where('institution_id', $institution->id)->firstOrFail();
        $this->assertTrue($config->use_field_mapping);
        $this->assertSame('gis_status', $config->field_mapping['status']);
        $this->assertSame('gis_deadline', $config->field_mapping['completion_deadline']);
        $this->assertSame('gis_description', $config->field_mapping['description']);
        $this->assertSame('custom', $config->field_mapping['reporter_value_mode']);
        $this->assertSame('Dispečeris', $config->field_mapping['reporter_default_value']);
        $this->assertSame('https://example.test/FeatureServer/queryDomains', $config->metadata_url);
        $this->assertSame('0', $config->metadata_layer_id);
        $this->assertSame(
            'Neveikia šviestuvas',
            $config->field_mapping['category_value_mapping'][(string) $nature->id]['code']
        );
        $response->assertJsonPath(
            'gis.field_mapping.category_value_mapping.'.$nature->id.'.code',
            'Neveikia šviestuvas'
        );
    }

    public function test_admin_can_fetch_gis_metadata_fields_and_domain_values(): void
    {
        $admin = $this->createSystemAdmin();

        $institution = Institution::create([
            'name' => 'GIS Institution',
            'contact_email' => 'gis@example.com',
        ]);
        InstitutionGisConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
            'technical_username' => 'arcgis-user',
            'technical_password' => 'secret',
            'client_type' => 'referer',
            'token_expiration_minutes' => 60,
            'feature_server_url' => 'https://example.test/FeatureServer',
            'attachment_url' => 'https://example.test/FeatureServer/252',
            'metadata_url' => 'https://metadata.example.test/queryDomains?f=pjson',
            'metadata_layer_id' => '252',
            'wkid' => 3346,
            'active_token' => 'cached-token',
            'active_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://example.test/FeatureServer/252*' => Http::response([
                'fields' => [
                    [
                        'name' => 'busena',
                        'alias' => 'Būsena',
                        'type' => 'esriFieldTypeString',
                        'domain' => ['name' => 'busena'],
                    ],
                    [
                        'name' => 'adresas',
                        'alias' => 'Adresas',
                        'type' => 'esriFieldTypeString',
                    ],
                ],
            ]),
            'https://metadata.example.test/queryDomains*' => Http::response([
                'domains' => [[
                    'type' => 'codedValue',
                    'name' => 'busena',
                    'description' => 'busena_duobes',
                    'codedValues' => [
                        ['name' => 'Registruojama', 'code' => 'Registruojama'],
                        ['name' => 'Atlikti darbai', 'code' => 'Atlikti darbai'],
                    ],
                ]],
            ]),
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson('/admin/institutions/'.$institution->id.'/gis-metadata');

        $response
            ->assertSuccessful()
            ->assertJsonPath('layer_id', '252')
            ->assertJsonPath('fields.0.name', 'busena')
            ->assertJsonPath('fields.0.coded_values.0.name', 'Registruojama')
            ->assertJsonPath('fields.1.name', 'adresas')
            ->assertJsonPath('fields.1.coded_values', []);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://metadata.example.test/queryDomains')
            && $request['layers'] === '252');
    }

    private function createSystemAdmin(): User
    {
        $user = User::factory()->create();
        $role = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_SYSTEM_ADMIN],
            ['name' => 'System administrator'],
        );
        $role->users()->attach($user->id);
        foreach (['admin.access', 'invitations.manage'] as $key) {
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
}

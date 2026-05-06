<?php

namespace Tests\Feature\Admin;

use App\Models\Institution;
use App\Models\InstitutionGisConfig;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                    'wkid' => 3346,
                    'use_field_mapping' => true,
                    'field_mapping' => [
                        'status' => 'gis_status',
                        'category' => 'gis_type',
                        'created_at' => 'gis_created',
                        'completion_deadline' => 'gis_deadline',
                        'address' => 'gis_address',
                        'reporter' => 'gis_reporter',
                        'internal_number' => 'gis_number',
                        'description' => 'gis_description',
                    ],
                ],
            ]);

        $response->assertSuccessful();

        $config = InstitutionGisConfig::where('institution_id', $institution->id)->firstOrFail();
        $this->assertTrue($config->use_field_mapping);
        $this->assertSame('gis_status', $config->field_mapping['status']);
        $this->assertSame('gis_deadline', $config->field_mapping['completion_deadline']);
        $this->assertSame('gis_description', $config->field_mapping['description']);
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

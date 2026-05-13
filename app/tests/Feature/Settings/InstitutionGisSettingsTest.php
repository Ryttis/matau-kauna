<?php

namespace Tests\Feature\Settings;

use App\Models\ClassifierValue;
use App\Models\Institution;
use App\Models\InstitutionGisConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionGisSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_gis_settings_can_be_disabled_without_arcgis_fields(): void
    {
        $institution = Institution::create([
            'name' => 'Test Institution',
            'code' => '123',
        ]);
        $user = User::factory()->create([
            'institution_id' => $institution->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('settings.institution'), [
                'name' => 'Updated Institution',
                'code' => '999',
                'gis' => [
                    'enabled' => false,
                ],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $institution->refresh();

        $this->assertSame('Updated Institution', $institution->name);
        $this->assertSame('999', $institution->code);
        $this->assertDatabaseMissing('institution_gis_configs', [
            'institution_id' => $institution->id,
        ]);
    }

    public function test_enabled_gis_settings_require_arcgis_fields(): void
    {
        $institution = Institution::create([
            'name' => 'Test Institution',
        ]);
        $user = User::factory()->create([
            'institution_id' => $institution->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('settings'))
            ->patch(route('settings.institution'), [
                'name' => 'Updated Institution',
                'code' => '999',
                'gis' => [
                    'enabled' => true,
                ],
            ]);

        $response
            ->assertRedirect(route('settings'))
            ->assertSessionHasErrors([
                'gis.generate_token_url',
                'gis.technical_username',
                'gis.client_type',
                'gis.token_expiration_minutes',
                'gis.service_base_url',
                'gis.service_layer_id',
            ]);

        $this->assertDatabaseMissing('institution_gis_configs', [
            'institution_id' => $institution->id,
        ]);
    }

    public function test_blank_password_preserves_existing_encrypted_password(): void
    {
        $institution = Institution::create([
            'name' => 'Test Institution',
        ]);
        $config = InstitutionGisConfig::create([
            'institution_id' => $institution->id,
            'enabled' => true,
            'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
            'technical_username' => 'arcgis-user',
            'technical_password' => 'secret-1',
            'client_type' => 'referer',
            'token_expiration_minutes' => 60,
            'feature_server_url' => 'https://example.test/FeatureServer/1',
            'attachment_url' => 'https://example.test/FeatureServer/1',
        ]);
        $user = User::factory()->create([
            'institution_id' => $institution->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('settings.institution'), [
                'name' => 'Updated Institution',
                'code' => '999',
                'gis' => [
                    'enabled' => true,
                    'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
                    'technical_username' => 'arcgis-user',
                    'technical_password' => '',
                    'client_type' => 'referer',
                    'token_expiration_minutes' => 120,
                    'feature_server_url' => 'https://example.test/FeatureServer/1',
                    'attachment_url' => 'https://example.test/FeatureServer/1',
                ],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $config->refresh();

        $this->assertSame('secret-1', $config->technical_password);
        $this->assertSame(120, $config->token_expiration_minutes);
    }

    public function test_service_base_url_and_layer_id_are_derived_for_institution_settings(): void
    {
        $institution = Institution::create([
            'name' => 'Test Institution',
        ]);
        $user = User::factory()->create([
            'institution_id' => $institution->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('settings.institution'), [
                'name' => 'Updated Institution',
                'gis' => [
                    'enabled' => true,
                    'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
                    'technical_username' => 'arcgis-user',
                    'technical_password' => 'secret-1',
                    'client_type' => 'referer',
                    'token_expiration_minutes' => 60,
                    'service_base_url' => 'https://example.test/arcgis/rest/services/Test/Service/',
                    'service_layer_id' => 1,
                    'wkid' => 3346,
                ],
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $config = InstitutionGisConfig::where('institution_id', $institution->id)->firstOrFail();
        $this->assertSame('https://example.test/arcgis/rest/services/Test/Service/FeatureServer/1', $config->feature_server_url);
        $this->assertSame($config->feature_server_url, $config->attachment_url);
        $this->assertSame('https://example.test/arcgis/rest/services/Test/Service/FeatureServer/queryDomains', $config->metadata_url);
        $this->assertSame('1', $config->metadata_layer_id);
    }

    public function test_wkid_is_persisted_on_settings_update(): void
    {
        $institution = Institution::create([
            'name' => 'Test Institution',
        ]);
        $user = User::factory()->create([
            'institution_id' => $institution->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('settings.institution'), [
                'name' => 'Updated Institution',
                'gis' => [
                    'enabled' => true,
                    'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
                    'technical_username' => 'arcgis-user',
                    'technical_password' => 'secret-1',
                    'client_type' => 'referer',
                    'token_expiration_minutes' => 60,
                    'feature_server_url' => 'https://example.test/FeatureServer/1',
                    'attachment_url' => 'https://example.test/FeatureServer/1',
                    'wkid' => 3857,
                ],
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('institution_gis_configs', [
            'institution_id' => $institution->id,
            'wkid' => 3857,
        ]);
    }

    public function test_wkid_rejects_unsupported_value(): void
    {
        $institution = Institution::create([
            'name' => 'Test Institution',
        ]);
        $user = User::factory()->create([
            'institution_id' => $institution->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('settings'))
            ->patch(route('settings.institution'), [
                'name' => 'Updated Institution',
                'gis' => [
                    'enabled' => true,
                    'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
                    'technical_username' => 'arcgis-user',
                    'technical_password' => 'secret',
                    'client_type' => 'referer',
                    'token_expiration_minutes' => 60,
                    'feature_server_url' => 'https://example.test/FeatureServer/1',
                    'attachment_url' => 'https://example.test/FeatureServer/1',
                    'wkid' => 9999,
                ],
            ]);

        $response->assertSessionHasErrors(['gis.wkid']);
    }

    public function test_field_mapping_is_persisted_on_settings_update(): void
    {
        $institution = Institution::create([
            'name' => 'Test Institution',
        ]);
        $nature = ClassifierValue::create([
            'name' => 'Neveikianti lempa',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'institution_id' => $institution->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('settings.institution'), [
                'name' => 'Updated Institution',
                'gis' => [
                    'enabled' => true,
                    'generate_token_url' => 'https://digital.kaunas.lt/portal/sharing/rest/generateToken',
                    'technical_username' => 'arcgis-user',
                    'technical_password' => 'secret-1',
                    'client_type' => 'referer',
                    'token_expiration_minutes' => 60,
                    'feature_server_url' => 'https://example.test/FeatureServer/1',
                    'attachment_url' => 'https://example.test/FeatureServer/1',
                    'wkid' => 3346,
                    'use_field_mapping' => true,
                    'field_mapping' => [
                        'status' => 'gis_status',
                        'category' => 'gis_category',
                        'created_at' => 'gis_created',
                        'completion_deadline' => 'gis_deadline',
                        'address' => 'gis_address',
                        'reporter' => 'gis_reporter',
                        'status_value_mapping' => [
                            'nauja' => [
                                'code' => 'Registruojama',
                                'name' => 'Registruojama',
                            ],
                        ],
                        'category_value_mapping' => [
                            (string) $nature->id => [
                                'code' => 'Neveikia šviestuvas',
                                'name' => 'Neveikia šviestuvas',
                            ],
                        ],
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $config = InstitutionGisConfig::where('institution_id', $institution->id)->firstOrFail();
        $this->assertTrue($config->use_field_mapping);
        $this->assertSame('gis_status', $config->field_mapping['status']);
        $this->assertSame('gis_deadline', $config->field_mapping['completion_deadline']);
        $this->assertSame(
            'Neveikia šviestuvas',
            $config->field_mapping['category_value_mapping'][(string) $nature->id]['code']
        );
        $this->assertSame('Registruojama', $config->field_mapping['status_value_mapping']['nauja']['code']);
    }
}

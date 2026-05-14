<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\InstitutionDvsConfig;
use Illuminate\Validation\Rule;

trait InstitutionRules
{
    protected function institutionRules(): array
    {
        $gisEnabled = filter_var(data_get($this->all(), 'gis.enabled', false), FILTER_VALIDATE_BOOLEAN);
        $featureServerUrlProvided = filled(data_get($this->all(), 'gis.feature_server_url'));
        $serviceBaseUrl = (string) data_get($this->all(), 'gis.service_base_url', '');
        $serviceBaseUrlHasLayer = preg_match('~/FeatureServer/\d+(?:/|$|[?#])~i', $serviceBaseUrl) === 1;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['required', 'string', 'email', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'integration_email' => ['nullable', 'string', 'email', 'max:255'],
            'integration_dvs' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_email_only' => ['boolean'],

            'dvs' => ['nullable', 'array'],
            'dvs.enabled' => ['nullable', 'boolean'],
            'dvs.complaint_api' => [
                'nullable',
                'string',
                Rule::in([
                    InstitutionDvsConfig::COMPLAINT_API_GAUNAMI_DOKUMENTAI,
                    InstitutionDvsConfig::COMPLAINT_API_PILIECIAI_LAISKAI,
                ]),
            ],
            'dvs.token_url' => ['nullable', 'url', 'max:512'],
            'dvs.username' => ['nullable', 'string', 'max:255'],
            'dvs.password' => ['nullable', 'string', 'max:255'],
            'dvs.client_id' => ['nullable', 'string', 'max:255'],
            'dvs.client_secret' => ['nullable', 'string', 'max:255'],
            'dvs.gaunami_dokumentai_url' => ['nullable', 'url', 'max:512'],
            'dvs.pilieciu_laiskai_url' => ['nullable', 'url', 'max:512'],
            'dvs.rusis_url' => ['nullable', 'url', 'max:512'],
            'dvs.rusis_kodas' => ['nullable', 'string', 'max:255'],
            'dvs.rastines_id' => ['nullable', 'string', 'max:255'],
            'dvs.registras_id' => ['nullable', 'string', 'max:255'],
            'dvs.darbuotojo_kodas' => ['nullable', 'string', 'max:255'],
            'dvs.sudarytojas_kodas' => ['nullable', 'string', 'max:255'],
            'dvs.gaunami_registro_kodas' => ['nullable', 'string', 'max:255'],
            'dvs.gaunami_registro_id' => ['nullable', 'string', 'max:255'],
            'dvs.gaunami_nukreipto_id' => ['nullable', 'string', 'max:255'],
            'dvs.gaunami_sudarytojas_id' => ['nullable', 'string', 'max:255'],
            'dvs.pilieciu_registro_kodas' => ['nullable', 'string', 'max:255'],
            'dvs.pilieciu_registro_id' => ['nullable', 'string', 'max:255'],
            'dvs.pilieciu_nukreipto_id' => ['nullable', 'string', 'max:255'],
            'dvs.pilieciu_sudarytojas_id' => ['nullable', 'string', 'max:255'],

            'gis' => ['nullable', 'array'],
            'gis.enabled' => ['nullable', 'boolean'],
            'gis.generate_token_url' => [Rule::requiredIf($gisEnabled), 'nullable', 'url', 'max:512'],
            'gis.technical_username' => [Rule::requiredIf($gisEnabled), 'nullable', 'string', 'max:255'],
            'gis.technical_password' => ['nullable', 'string', 'max:255'],
            'gis.client_type' => [Rule::requiredIf($gisEnabled), 'nullable', 'string', 'max:64'],
            'gis.token_expiration_minutes' => [Rule::requiredIf($gisEnabled), 'nullable', 'integer', 'min:1', 'max:1440'],
            'gis.service_base_url' => [Rule::requiredIf($gisEnabled && ! $featureServerUrlProvided), 'nullable', 'url', 'max:512'],
            'gis.service_layer_id' => [Rule::requiredIf($gisEnabled && ! $featureServerUrlProvided && ! $serviceBaseUrlHasLayer), 'nullable', 'integer', 'min:0'],
            'gis.feature_server_url' => ['nullable', 'url', 'max:512'],
            'gis.attachment_url' => ['nullable', 'url', 'max:512'],
            'gis.metadata_url' => ['nullable', 'url', 'max:512'],
            'gis.metadata_layer_id' => ['nullable', 'string', 'max:64'],
            'gis.wkid' => ['nullable', 'integer', 'in:3346,4326,3857'],
            'gis.use_field_mapping' => ['nullable', 'boolean'],
            'gis.field_mapping' => ['nullable', 'array'],
            'gis.field_mapping.status' => ['nullable', 'string', 'max:128'],
            'gis.field_mapping.category' => ['nullable', 'string', 'max:128'],
            'gis.field_mapping.created_at' => ['nullable', 'string', 'max:128'],
            'gis.field_mapping.completion_deadline' => ['nullable', 'string', 'max:128'],
            'gis.field_mapping.address' => ['nullable', 'string', 'max:128'],
            'gis.field_mapping.reporter' => ['nullable', 'string', 'max:128'],
            'gis.field_mapping.internal_number' => ['nullable', 'string', 'max:128'],
            'gis.field_mapping.description' => ['nullable', 'string', 'max:128'],
            'gis.field_mapping.category_value_mapping' => ['nullable', 'array'],
            'gis.field_mapping.category_value_mapping.*.code' => ['nullable', 'string', 'max:255'],
            'gis.field_mapping.category_value_mapping.*.name' => ['nullable', 'string', 'max:255'],
            'gis.field_mapping.status_value_mapping' => ['nullable', 'array'],
            'gis.field_mapping.status_value_mapping.*.code' => ['nullable', 'string', 'max:255'],
            'gis.field_mapping.status_value_mapping.*.name' => ['nullable', 'string', 'max:255'],
            'gis.field_mapping.reporter_value_mode' => ['nullable', 'string', 'in:full_name,resident,custom'],
            'gis.field_mapping.reporter_default_value' => ['nullable', 'string', 'max:255'],
        ];
    }
}

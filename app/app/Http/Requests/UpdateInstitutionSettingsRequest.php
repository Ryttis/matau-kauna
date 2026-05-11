<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInstitutionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $gisEnabled = filter_var(data_get($this->all(), 'gis.enabled', false), FILTER_VALIDATE_BOOLEAN);

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'gis' => ['nullable', 'array'],
            'gis.enabled' => ['nullable', 'boolean'],
            'gis.generate_token_url' => [Rule::requiredIf($gisEnabled), 'nullable', 'url', 'max:512'],
            'gis.technical_username' => [Rule::requiredIf($gisEnabled), 'nullable', 'string', 'max:255'],
            'gis.technical_password' => ['nullable', 'string', 'max:255'],
            'gis.client_type' => [Rule::requiredIf($gisEnabled), 'nullable', 'string', 'max:64'],
            'gis.token_expiration_minutes' => [Rule::requiredIf($gisEnabled), 'nullable', 'integer', 'min:1', 'max:1440'],
            'gis.feature_server_url' => [Rule::requiredIf($gisEnabled), 'nullable', 'url', 'max:512'],
            'gis.attachment_url' => [Rule::requiredIf($gisEnabled), 'nullable', 'url', 'max:512'],
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
            'gis.field_mapping.reporter_value_mode' => ['nullable', 'string', 'in:full_name,resident,custom'],
            'gis.field_mapping.reporter_default_value' => ['nullable', 'string', 'max:255'],
        ];
    }
}

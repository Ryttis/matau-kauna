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
            'gis.wkid' => ['nullable', 'integer', 'in:3346,4326,3857'],
        ];
    }
}

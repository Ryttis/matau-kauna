<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AuditUpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'retention_is_active' => ['sometimes', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateArchiveRetentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'in:user,institution,problem,classifier_value'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['boolean'],
        ];
    }
}

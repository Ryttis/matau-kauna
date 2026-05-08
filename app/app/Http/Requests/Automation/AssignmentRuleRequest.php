<?php

namespace App\Http\Requests\Automation;

use Illuminate\Foundation\Http\FormRequest;

class AssignmentRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
            'is_active' => ['boolean'],
        ];
    }
}

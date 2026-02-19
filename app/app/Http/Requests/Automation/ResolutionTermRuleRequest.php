<?php

namespace App\Http\Requests\Automation;

use Illuminate\Foundation\Http\FormRequest;

class ResolutionTermRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'category' => ['nullable', 'string', 'max:100'],
            'problem_type_id' => ['nullable', 'integer', 'exists:classifier_values,id'],
            'problem_nature_id' => ['nullable', 'integer', 'exists:classifier_values,id'],
            'is_active' => ['boolean'],
        ];
    }
}

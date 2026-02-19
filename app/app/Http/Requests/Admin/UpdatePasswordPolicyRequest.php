<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'min_length' => ['required', 'integer', 'min:6', 'max:128'],
            'require_lowercase' => ['boolean'],
            'require_uppercase' => ['boolean'],
            'require_special' => ['boolean'],
            'two_factor_can_enable' => ['boolean'],
            'prohibit_name_email' => ['boolean'],
        ];
    }
}

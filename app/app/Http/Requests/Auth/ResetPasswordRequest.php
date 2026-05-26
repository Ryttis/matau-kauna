<?php

namespace App\Http\Requests\Auth;

use App\Models\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $policy = PasswordPolicy::get();

        return [
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => $policy->passwordRules(),
        ];
    }

    public function messages(): array
    {
        return PasswordPolicy::get()->validationMessagesForPasswordField();
    }
}

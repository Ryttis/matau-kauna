<?php

namespace App\Http\Requests\Auth;

use App\Models\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class RegisterFromInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $policy = PasswordPolicy::get();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $policy->passwordRules(),
        ];
    }

    public function messages(): array
    {
        return PasswordPolicy::get()->validationMessagesForPasswordField();
    }
}

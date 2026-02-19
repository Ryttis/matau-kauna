<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
            'role_id' => ['required', 'uuid', 'exists:user_role,id'],
        ];
    }
}

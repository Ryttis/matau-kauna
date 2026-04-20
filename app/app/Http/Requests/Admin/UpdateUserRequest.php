<?php

namespace App\Http\Requests\Admin;

use App\Models\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $policy = PasswordPolicy::get();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'password' => array_merge(['nullable'], array_diff($policy->rules(), ['required'])),
            'password_confirmation' => ['nullable', 'same:password'],
        ];

        return $rules;
    }

    public function messages(): array
    {
        return PasswordPolicy::get()->validationMessagesForPasswordField();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->input('password'))) {
                return;
            }
            $policy = PasswordPolicy::get();
            if (! $policy->validateProhibitNameEmail(
                $this->input('password'),
                $this->input('name'),
                $this->input('email')
            )) {
                $validator->errors()->add(
                    'password',
                    'Slaptažodyje negalima naudoti vardo, pavardės ar el. pašto adreso.'
                );
            }
        });
    }
}

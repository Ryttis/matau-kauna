<?php

namespace App\Http\Requests\Admin;

use App\Models\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
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
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => $policy->rules(),
            'password_confirmation' => ['required_with:password', 'same:password'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'role_id' => ['required', 'uuid', 'exists:user_role,id'],
        ];
    }

    public function messages(): array
    {
        $policy = PasswordPolicy::get();

        return array_merge(
            $policy->validationMessagesForPasswordField(),
            [
                'name.required' => 'Vardas privalomas.',
                'name.max' => 'Vardas per ilgas.',
                'surname.max' => 'Pavardė per ilga.',
                'email.required' => 'El. paštas privalomas.',
                'email.email' => 'Neteisingas el. pašto formatas.',
                'email.unique' => 'Toks el. paštas jau naudojamas.',
                'email.max' => 'El. paštas per ilgas.',
                'password.required' => 'Slaptažodis privalomas.',
                'institution_id.exists' => 'Pasirinkta įstaiga neegzistuoja.',
                'role_id.required' => 'Rolė privaloma.',
                'role_id.exists' => 'Pasirinkta rolė neegzistuoja.',
            ],
        );
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

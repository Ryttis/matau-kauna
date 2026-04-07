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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => $policy->rules(),
            'password_confirmation' => ['required_with:password', 'same:password'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['uuid', 'exists:user_role,id'],
        ];
    }

    public function messages(): array
    {
        $min = PasswordPolicy::get()->min_length;

        return [
            'name.required' => 'Vardas privalomas.',
            'name.max' => 'Vardas per ilgas.',
            'email.required' => 'El. paštas privalomas.',
            'email.email' => 'Neteisingas el. pašto formatas.',
            'email.unique' => 'Toks el. paštas jau naudojamas.',
            'email.max' => 'El. paštas per ilgas.',
            'password.required' => 'Slaptažodis privalomas.',
            'password.min' => "Slaptažodis turi būti bent {$min} simbolių.",
            'password.confirmed' => 'Slaptažodžiai nesutampa.',
            'password.regex' => 'Slaptažodis turi turėti mažąją raidę, didžiąją raidę ir specialų simbolį (pvz. !@#$%).',
            'password_confirmation.required_with' => 'Pakartokite slaptažodį.',
            'password_confirmation.same' => 'Slaptažodžiai nesutampa.',
            'institution_id.exists' => 'Pasirinkta įstaiga neegzistuoja.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
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

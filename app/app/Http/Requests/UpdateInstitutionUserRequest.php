<?php

namespace App\Http\Requests;

use App\Models\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInstitutionUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $policy = PasswordPolicy::get();

        return [
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => $policy->passwordRules(required: false),
            'password_confirmation' => ['nullable', 'same:password'],
        ];
    }

    public function messages(): array
    {
        return array_merge(
            PasswordPolicy::get()->validationMessagesForPasswordField(),
            [
                'name.required' => 'Vardas privalomas.',
                'name.max' => 'Vardas per ilgas.',
                'surname.max' => 'Pavardė per ilga.',
            ]
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

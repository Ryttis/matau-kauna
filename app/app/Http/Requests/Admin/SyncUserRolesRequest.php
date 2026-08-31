<?php

namespace App\Http\Requests\Admin;

use App\Models\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('role_ids') && $this->filled('role_id')) {
            $this->merge([
                'role_ids' => [$this->input('role_id')],
            ]);
        }

        $ids = $this->input('role_ids');
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            if (is_array($decoded)) {
                $this->merge(['role_ids' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['uuid', 'distinct', 'exists:user_role,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'role_ids' => 'rolės',
            'role_ids.*' => 'rolė',
        ];
    }

    public function messages(): array
    {
        return [
            'role_ids.required' => 'Pasirinkite bent vieną rolę.',
            'role_ids.array' => 'Rolės turi būti pateiktos teisingai.',
            'role_ids.min' => 'Pasirinkite bent vieną rolę.',
            'role_ids.*.uuid' => 'Netinkama rolės reikšmė.',
            'role_ids.*.exists' => 'Viena iš pasirinktų rolių neegzistuoja.',
            'role_ids.*.distinct' => 'Tą pačią rolę galima pasirinkti tik vieną kartą.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ids = $this->input('role_ids', []);
            if (! is_array($ids)) {
                return;
            }

            $unique = array_values(array_unique($ids));
            $roles = UserRole::query()->whereIn('id', $unique)->get(['id', 'role']);

            if ($roles->count() !== count($unique)) {
                $validator->errors()->add('role_ids', 'Neteisingos rolės.');

                return;
            }

            $constants = $roles->pluck('role')->all();
            $hasReporter = in_array(UserRole::ROLE_REPORTER, $constants, true);

            // Vienintelė išorinė rolė yra pareiškėjas — visos kitos (įskaitant per RBAC
            // sukūrtas) yra vidinės ir gali būti derinamos tarpusavyje.
            if ($hasReporter && count($constants) > 1) {
                $validator->errors()->add('role_ids', 'Pareiškėjo rolė negali būti derinama su kitomis rolėmis.');
            }
        });
    }
}

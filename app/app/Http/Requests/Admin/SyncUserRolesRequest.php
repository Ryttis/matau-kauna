<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRoleEnum;
use App\Models\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'uuid', 'exists:user_role,id'],
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

            if ($hasReporter && count($constants) > 1) {
                $validator->errors()->add('role_ids', 'Pareiškėjo rolė negali būti derinama su kitomis rolėmis.');

                return;
            }

            if (count($constants) > 1) {
                foreach ($constants as $constant) {
                    if (! in_array($constant, UserRoleEnum::staffValues(), true)) {
                        $validator->errors()->add('role_ids', 'Kelių rolių derinimas galimas tik vidinėms rolėms.');

                        return;
                    }
                }
            }
        });
    }
}

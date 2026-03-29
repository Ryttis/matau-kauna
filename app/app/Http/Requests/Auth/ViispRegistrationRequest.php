<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ViispRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $phone = $this->input('phone');
            if (! is_string($phone) || $phone === '') {
                return;
            }
            if (! self::isValidLithuanianPhone($phone)) {
                $v->errors()->add(
                    'phone',
                    'Įveskite lietuvišką telefono numerį (pvz. 067670895, +370 676 70895 arba 8 676 70895).'
                );
            }
        });
    }

    /**
     * Priima įprastus LT formatus: 067670895, +370 676 70895, 867670895, 67670895;
     * skaitmenys gali būti su tarpais / brūkšniais / „+“.
     */
    private static function isValidLithuanianPhone(string $value): bool
    {
        $d = preg_replace('/\D/', '', $value);

        if ($d === '') {
            return false;
        }

        if (preg_match('/^370\d{8}$/', $d)) {
            return true;
        }

        if (preg_match('/^0[1-9]\d{7,9}$/', $d)) {
            return true;
        }

        if (preg_match('/^8\d{8}$/', $d)) {
            return true;
        }

        return (bool) preg_match('/^6\d{7}$/', $d);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\s\-]+$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'vardas',
            'surname' => 'pavardė',
            'email' => 'el. paštas',
            'phone' => 'telefono numeris',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Įveskite vardą.',
            'name.max' => 'Vardas negali būti ilgesnis nei :max simbolių.',
            'surname.required' => 'Įveskite pavardę.',
            'surname.max' => 'Pavardė negali būti ilgesnė nei :max simbolių.',
            'email.required' => 'Įveskite el. pašto adresą.',
            'email.email' => 'Įveskite teisingą el. pašto adresą.',
            'email.unique' => 'Šiuo el. paštu paskyra jau užregistruota.',
            'email.max' => 'El. paštas negali būti ilgesnis nei :max simbolių.',
            'phone.max' => 'Telefono numeris negali būti ilgesnis nei :max simbolių.',
            'phone.regex' => 'Telefono numerį įveskite skaitmenimis; galimi simboliai: +, tarpas, brūkšnys.',
        ];
    }
}

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
            if (! self::isValidLithuanianPhone($phone) && ! self::isValidInternationalPhoneDigits($phone)) {
                $v->errors()->add(
                    'phone',
                    'Įveskite teisingą numerį: pasirinkite šalį ir užpildykite lauką, arba palikite telefoną tuščią.'
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

    private static function isValidInternationalPhoneDigits(string $value): bool
    {
        $d = preg_replace('/\D/', '', $value);
        if ($d === '') {
            return false;
        }

        $rules = [
            ['dial' => '353', 'min' => 7, 'max' => 9],
            ['dial' => '372', 'min' => 7, 'max' => 8],
            ['dial' => '371', 'min' => 8, 'max' => 8],
            ['dial' => '370', 'min' => 8, 'max' => 8],
            ['dial' => '49', 'min' => 10, 'max' => 11],
            ['dial' => '48', 'min' => 9, 'max' => 9],
            ['dial' => '44', 'min' => 10, 'max' => 10],
            ['dial' => '34', 'min' => 9, 'max' => 9],
            ['dial' => '33', 'min' => 9, 'max' => 9],
            ['dial' => '31', 'min' => 9, 'max' => 9],
        ];

        usort($rules, static fn (array $a, array $b): int => strlen($b['dial']) <=> strlen($a['dial']));

        foreach ($rules as $row) {
            $prefix = $row['dial'];
            if (! str_starts_with($d, $prefix)) {
                continue;
            }
            $nationalLen = strlen($d) - strlen($prefix);

            return $nationalLen >= $row['min'] && $nationalLen <= $row['max'];
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\s\-]+$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'el. paštas',
            'phone' => 'telefono numeris',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Įveskite el. pašto adresą.',
            'email.email' => 'Įveskite teisingą el. pašto adresą.',
            'email.unique' => 'Šiuo el. paštu paskyra jau užregistruota.',
            'email.max' => 'El. paštas negali būti ilgesnis nei :max simbolių.',
            'phone.max' => 'Telefono numeris negali būti ilgesnis nei :max simbolių.',
            'phone.regex' => 'Telefono numerį įveskite skaitmenimis; galimi simboliai: +, tarpas, brūkšnys.',
        ];
    }
}

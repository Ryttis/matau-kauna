<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\InstitutionRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInstitutionRequest extends FormRequest
{
    use InstitutionRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->institutionRules();
    }
}

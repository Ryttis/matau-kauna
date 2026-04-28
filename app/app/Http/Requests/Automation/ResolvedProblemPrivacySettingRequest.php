<?php

namespace App\Http\Requests\Automation;

use App\Models\ResolvedProblemPrivacySetting;
use Illuminate\Foundation\Http\FormRequest;

class ResolvedProblemPrivacySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days_after_resolution' => [
                'required',
                'integer',
                'min:'.ResolvedProblemPrivacySetting::DAYS_AFTER_RESOLUTION_MIN,
                'max:'.ResolvedProblemPrivacySetting::DAYS_AFTER_RESOLUTION_MAX,
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

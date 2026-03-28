<?php

namespace App\Http\Requests\Automation;

use App\Models\InactiveReporterDeletionSetting;
use Illuminate\Foundation\Http\FormRequest;

class InactiveReporterSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inactivity_days' => [
                'required',
                'integer',
                'min:'.InactiveReporterDeletionSetting::INACTIVITY_DAYS_MIN,
                'max:'.InactiveReporterDeletionSetting::INACTIVITY_DAYS_MAX,
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

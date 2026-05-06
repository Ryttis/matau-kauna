<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePublicStatisticsConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'config' => ['required', 'array'],
            'config.kpis' => ['array'],
            'config.kpis.*' => ['string', 'in:received_reports,avg_resolution_days,resolved_problems,resolved_on_time,monthly_reports,problematic_categories,effectively_resolved'],
            'config.date_from' => ['nullable', 'date_format:Y-m-d'],
            'config.date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:config.date_from'],
            'config.show_category_filter' => ['boolean'],
        ];
    }
}

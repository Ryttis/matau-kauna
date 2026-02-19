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
            'config.default_period' => ['string', 'in:week,month,quarter,year,all'],
            'config.period_options' => ['array'],
            'config.period_options.*' => ['string', 'in:week,month,quarter,year,all'],
            'config.show_category_filter' => ['boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AuditExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'event_type' => ['nullable', 'string', 'in:login,logout,user_action,integration,system,audit_config'],
            'result' => ['nullable', 'string', 'in:success,failure'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}

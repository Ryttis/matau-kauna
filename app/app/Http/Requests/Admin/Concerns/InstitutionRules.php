<?php

namespace App\Http\Requests\Admin\Concerns;

trait InstitutionRules
{
    protected function institutionRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['required', 'string', 'email', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'integration_email' => ['nullable', 'string', 'email', 'max:255'],
            'integration_dvs' => ['nullable', 'string', 'max:255'],
            'integration_gis' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_email_only' => ['boolean'],
        ];
    }
}

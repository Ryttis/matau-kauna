<?php

namespace App\Http\Controllers\Concerns;

trait AuthorizesAutomation
{
    protected function authorizeAutomation(): void
    {
        if (! request()->user()?->isProblemAdminOrSystemAdmin()) {
            abort(403);
        }
    }
}

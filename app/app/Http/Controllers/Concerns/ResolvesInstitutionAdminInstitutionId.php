<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Http\Request;

trait ResolvesInstitutionAdminInstitutionId
{
    protected function resolveInstitutionId(Request $request, User $user): int
    {
        $isSystemAdmin = $user->canAccessAdmin();

        $institutionId = null;
        if ($isSystemAdmin) {
            $institutionId = $request->integer('institution_id') ?: null;
            if ($institutionId) {
                $request->validate([
                    'institution_id' => ['integer', 'exists:institutions,id'],
                ]);
            }
        }

        $institutionId = $institutionId ?? $user->institution_id ?? Institution::query()->notArchived()->value('id');

        if (! $institutionId) {
            abort(403);
        }

        return (int) $institutionId;
    }
}

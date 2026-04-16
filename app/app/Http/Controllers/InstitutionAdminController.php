<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InstitutionAdminController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if (! ($user instanceof User) || ! $user->canManageTeams()) {
            abort(403);
        }

        $isSystemAdmin = $user->canAccessAdmin();

        $institutionId = null;
        if ($isSystemAdmin) {
            $institutionId = $request->integer('institution_id');
            if ($institutionId) {
                $request->validate([
                    'institution_id' => ['integer', 'exists:institutions,id'],
                ]);
            }
        }

        $institutionId = $institutionId ?? $user->institution_id ?? Institution::query()->value('id');

        if (! $institutionId) {
            abort(403);
        }

        return Inertia::render('InstitutionAdmin/Index', [
            'initialInstitutionId' => $institutionId,
        ]);
    }
}

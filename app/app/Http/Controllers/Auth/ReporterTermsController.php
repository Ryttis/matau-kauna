<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReporterTermsController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasRole(UserRole::ROLE_REPORTER)) {
            return redirect()->route('dashboard');
        }

        if ($user->terms_accepted_at !== null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/ReporterTerms');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasRole(UserRole::ROLE_REPORTER)) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'accepted' => ['required', 'accepted'],
        ], [
            'accepted.accepted' => 'Turite sutikti su taisyklėmis.',
        ]);

        $user->update(['terms_accepted_at' => now()]);

        return redirect()->route('dashboard');
    }
}

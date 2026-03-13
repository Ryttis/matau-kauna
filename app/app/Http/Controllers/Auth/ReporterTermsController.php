<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ContentVersion;
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
        $redirect = $this->ensureReporter($user);
        if ($redirect) {
            return $redirect;
        }
        if ($user->terms_accepted_at !== null) {
            return redirect()->route('dashboard');
        }

        $termsContent = ContentVersion::getLatestPublished(ContentVersion::TYPE_TERMS);

        return Inertia::render('Auth/ReporterTerms', [
            'terms_title' => (string) ($termsContent?->title ?? 'Sistemos taisyklės ir asmens duomenų tvarkymas'),
            'terms_body' => (string) ($termsContent?->body ?? ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $redirect = $this->ensureReporter($user);
        if ($redirect) {
            return $redirect;
        }

        $request->validate([
            'accepted' => ['required', 'accepted'],
        ], [
            'accepted.accepted' => 'Turite sutikti su taisyklėmis.',
        ]);

        $user->update(['terms_accepted_at' => now()]);

        return redirect()->route('dashboard');
    }

    private function ensureReporter($user): ?RedirectResponse
    {
        if (! $user->hasRole(UserRole::ROLE_REPORTER)) {
            return redirect()->route('dashboard');
        }

        return null;
    }
}

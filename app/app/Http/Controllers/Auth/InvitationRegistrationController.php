<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterFromInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvitationRegistrationController extends Controller
{
    public function __construct(
        private InvitationService $invitationService
    ) {}

    public function show(string $token): Response|RedirectResponse
    {
        $invitation = Invitation::with('institution')->where('token', $token)->firstOrFail();
        $redirect = $this->ensureValidInvitation($invitation);
        if ($redirect) {
            return $redirect;
        }

        return Inertia::render('Auth/RegisterInvite', [
            'token' => $token,
            'email' => $invitation->email,
            'institution_name' => $invitation->institution->name ?? '',
        ]);
    }

    public function store(RegisterFromInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = Invitation::with('institution')->where('token', $token)->firstOrFail();
        $redirect = $this->ensureValidInvitation($invitation);
        if ($redirect) {
            return $redirect;
        }

        $data = $request->validated();

        if (strtolower($data['email']) !== strtolower($invitation->email)) {
            return back()->withErrors(['email' => 'El. paštas turi atitikti pakvietime nurodytą.']);
        }

        if (User::where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'Naudotojas su tokiu el. paštu jau egzistuoja.']);
        }

        $user = $this->invitationService->registerFromInvitation($invitation, $data);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('active_role_id', $invitation->role_id);

        return redirect()->route('dashboard');
    }

    private function ensureValidInvitation(Invitation $invitation): ?RedirectResponse
    {
        if (! $invitation->isValid()) {
            return redirect()->route('login')->with('error', 'Pakvietimas nebegalioja arba jau panaudotas.');
        }

        return null;
    }
}

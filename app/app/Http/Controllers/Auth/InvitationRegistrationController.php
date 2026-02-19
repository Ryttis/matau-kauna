<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterFromInvitationRequest;
use App\Models\Employee;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class InvitationRegistrationController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = Invitation::with('institution')->where('token', $token)->firstOrFail();

        if (! $invitation->isValid()) {
            return redirect()->route('login')->with('error', 'Pakvietimas nebegalioja arba jau panaudotas.');
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

        if (! $invitation->isValid()) {
            return redirect()->route('login')->with('error', 'Pakvietimas nebegalioja arba jau panaudotas.');
        }

        $data = $request->validated();

        if (strtolower($data['email']) !== strtolower($invitation->email)) {
            return back()->withErrors(['email' => 'El. paštas turi atitikti pakvietime nurodytą.']);
        }

        if (User::where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'Naudotojas su tokiu el. paštu jau egzistuoja.']);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'institution_id' => $invitation->institution_id,
        ]);

        $user->roles()->sync([$invitation->role_id]);

        if ($invitation->institution_id) {
            Employee::firstOrCreate(
                [
                    'email' => $user->email,
                    'institution_id' => $invitation->institution_id,
                ],
                [
                    'name' => $user->name ?? '',
                    'surname' => '',
                ]
            );
        }

        $invitation->update(['used_at' => now()]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('active_role_id', $invitation->role_id);

        return redirect()->route('dashboard');
    }
}

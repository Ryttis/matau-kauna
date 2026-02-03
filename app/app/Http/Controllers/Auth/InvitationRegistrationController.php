<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
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

    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::with('institution')->where('token', $token)->firstOrFail();

        if (! $invitation->isValid()) {
            return redirect()->route('login')->with('error', 'Pakvietimas nebegalioja arba jau panaudotas.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (strtolower($data['email']) !== strtolower($invitation->email)) {
            return back()->withErrors(['email' => 'El. paštas turi atitikti pakvietime nurodytą.']);
        }

        if (User::where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'Naudotojas su tokiu el. paštu jau egzistuoja.']);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
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

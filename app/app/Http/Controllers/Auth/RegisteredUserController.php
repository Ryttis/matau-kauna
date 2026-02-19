<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $reporterRole = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_REPORTER],
            ['name' => 'Pareiškėjas']
        );
        $user->roles()->sync([$reporterRole->id]);

        $user->sendEmailVerificationNotification();

        return redirect()->route('login')->with('status', 'Registracija sėkminga. Patikrinkite el. paštą ir patvirtinkite paskyrą.');
    }
}

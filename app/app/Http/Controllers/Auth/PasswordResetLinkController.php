<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $user = User::query()
            ->with('roles:id,role')
            ->whereRaw('LOWER(email) = ?', [$request->validated('email')])
            ->first();

        if (! $user?->canRequestPasswordResetLink()) {
            throw ValidationException::withMessages([
                'email' => ['Su tokiu el. paÅ¡tu naudotojo nÄ—ra.'],
            ]);
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Nuoroda išsiųsta į nurodytą el. paštą. Patikrinkite paštą.');
        }

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => ['Siųsti daugiau negalima. Pabandykite po minutės.'],
            ]);
        }

        throw ValidationException::withMessages([
            'email' => ['Su tokiu el. paštu naudotojo nėra.'],
        ]);
    }
}

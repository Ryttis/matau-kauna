<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user()->load('institution');
        $employees = Employee::query()
            ->where('institution_id', $user->institution_id)
            ->get();

        return Inertia::render('Settings', [
            'employees' => $employees,
        ]);
    }

    public function changePassword(): Response
    {
        return Inertia::render('Settings/ChangePassword');
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . Auth::id()],
        ]);

        Auth::user()->update($validated);

        return back();
    }

    public function storeEmployee(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $validated['institution_id'] = Auth::user()->institution_id;
        Employee::create($validated);

        return back();
    }

    public function updateInstitution(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();
        if ($user->institution) {
            $user->institution->update($validated);
        } else {
            $institution = Institution::create($validated);
            $user->update(['institution_id' => $institution->id]);
        }

        return back();
    }

    public function updatePrivacy(Request $request)
    {
        $validated = $request->validate([
            'two_factor_enabled' => ['boolean'],
            'email_notifications_enabled' => ['boolean'],
            'email_updates_institution_admin' => ['sometimes', 'boolean'],
        ]);

        Auth::user()->update($validated);

        return back();
    }

    public function sendPasswordResetLink(Request $request)
    {
        $email = $request->user()->email;

        $status = Password::sendResetLink(['email' => $email]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Išsiuntėme slaptažodžio keitimo nuorodą į jūsų el. paštą.');
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->with('status', 'Nuoroda jau buvo siųsta neseniai. Palaukite minutę ir bandykite dar kartą.');
        }

        return back()->withErrors(['email' => __($status)]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = Auth::user();
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

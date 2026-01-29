<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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
        ]);

        Auth::user()->update($validated);

        return back();
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back();
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

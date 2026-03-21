<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Institution;
use App\Models\InstitutionTeam;
use App\Models\PasswordPolicy;
use App\Models\UserRole;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    public function profile(): Response
    {
        return Inertia::render('Profile');
    }

    public function index(): Response
    {
        $data = $this->settingsService->getSettingsPageData(Auth::user());

        return Inertia::render('Settings', $data);
    }

    public function changePassword(): Response
    {
        return Inertia::render('Settings/ChangePassword');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $policy = PasswordPolicy::get();
        $user = $request->user();
        $rules = [
            'current_password' => ['required', 'current_password'],
            'password' => array_merge(['required'], $policy->rules()),
            'password_confirmation' => ['required', 'same:password'],
        ];
        $validated = $request->validate($rules);

        if (! $policy->validateProhibitNameEmail($validated['password'], $user->name, $user->email)) {
            return back()->withErrors([
                'password' => 'Slaptažodyje negalima naudoti vardo, pavardės ar el. pašto adreso.',
            ]);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return back()->with('status', 'Slaptažodis pakeistas.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.Auth::id()],
        ]);

        Auth::user()->update($validated);

        return back()->with('success', 'Profilis atnaujintas.');
    }

    public function storeEmployee(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $validated['institution_id'] = Auth::user()->institution_id;
        Employee::create($validated);

        return back()->with('success', 'Darbuotojas pridėtas.');
    }

    public function storeTeam(Request $request): mixed
    {
        $user = Auth::user();
        $this->ensureInstitutionAdmin($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $team = InstitutionTeam::create([
            'institution_id' => $user->institution_id,
            'name' => $validated['name'],
        ]);

        return $team->load('employees:id,name,surname,email');
    }

    public function updateTeam(Request $request, InstitutionTeam $team): mixed
    {
        $user = Auth::user();
        $this->ensureInstitutionAdmin($user);
        $this->ensureSameInstitution($user, $team);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $team->update($validated);

        return $team->fresh()->load('employees:id,name,surname,email');
    }

    public function destroyTeam(InstitutionTeam $team): mixed
    {
        $user = Auth::user();
        $this->ensureInstitutionAdmin($user);
        $this->ensureSameInstitution($user, $team);

        $team->delete();

        return response()->noContent();
    }

    public function syncTeamMembers(Request $request, InstitutionTeam $team): mixed
    {
        $user = Auth::user();
        $this->ensureInstitutionAdmin($user);
        $this->ensureSameInstitution($user, $team);

        $validated = $request->validate([
            'employee_ids' => ['array'],
            'employee_ids.*' => [
                'integer',
                Rule::exists('employees', 'id')->where('institution_id', $user->institution_id),
            ],
        ]);

        $team->employees()->sync($validated['employee_ids'] ?? []);

        return $team->fresh()->load('employees:id,name,surname,email');
    }

    public function updateInstitution(Request $request): RedirectResponse
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

        return back()->with('success', 'Įstaigos duomenys atnaujinti.');
    }

    public function updatePrivacy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'two_factor_enabled' => ['sometimes', 'boolean'],
            'email_notifications_enabled' => ['sometimes', 'boolean'],
            'email_updates_institution_admin' => ['sometimes', 'boolean'],
            'reporter_email_own_problems' => ['sometimes', 'boolean'],
            'reporter_email_watched_problems' => ['sometimes', 'boolean'],
        ]);

        $data = [];
        if (array_key_exists('two_factor_enabled', $validated)) {
            $data['two_factor_enabled'] = filter_var($validated['two_factor_enabled'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('email_notifications_enabled', $validated)) {
            $data['email_notifications_enabled'] = filter_var($validated['email_notifications_enabled'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('email_updates_institution_admin', $validated)) {
            $data['email_updates_institution_admin'] = filter_var($validated['email_updates_institution_admin'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('reporter_email_own_problems', $validated)) {
            $data['reporter_email_own_problems'] = filter_var($validated['reporter_email_own_problems'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('reporter_email_watched_problems', $validated)) {
            $data['reporter_email_watched_problems'] = filter_var($validated['reporter_email_watched_problems'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($data !== []) {
            Auth::user()->update($data);
        }

        return back()->with('success', 'Privatumo nustatymai atnaujinti.');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $email = $request->user()->email;

        $status = Password::sendResetLink(['email' => $email]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Nuoroda išsiųsta į jūsų el. paštą. Patikrinkite paštą.');
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->with('status', 'Pabandykite vėliau – nuoroda jau siųsta neseniai.');
        }

        return back()->withErrors(['email' => 'Nepavyko išsiųsti. Pabandykite vėliau.']);
    }

    public function destroy(Request $request): RedirectResponse
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

    private function ensureInstitutionAdmin($user): void
    {
        if (! $user || ! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN) || ! $user->institution_id) {
            abort(403);
        }
    }

    private function ensureSameInstitution($user, InstitutionTeam $team): void
    {
        if ((int) $team->institution_id !== (int) $user->institution_id) {
            abort(403);
        }
    }
}

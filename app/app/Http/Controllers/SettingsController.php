<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Institution;
use App\Models\InstitutionTeam;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
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

        $teams = InstitutionTeam::query()
            ->where('institution_id', $user->institution_id)
            ->with(['employees:id,name,surname,email'])
            ->orderBy('name')
            ->get();

        $inviteRoles = UserRole::query()
            ->whereIn('role', [UserRole::ROLE_INSTITUTION_ADMIN, UserRole::ROLE_EMPLOYEE])
            ->get(['id', 'role', 'name']);

        return Inertia::render('Settings', [
            'employees' => $employees,
            'teams' => $teams,
            'inviteRoles' => $inviteRoles,
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

    public function storeTeam(Request $request)
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

    public function updateTeam(Request $request, InstitutionTeam $team)
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

    public function destroyTeam(InstitutionTeam $team)
    {
        $user = Auth::user();
        $this->ensureInstitutionAdmin($user);
        $this->ensureSameInstitution($user, $team);

        $team->delete();

        return response()->noContent();
    }

    public function syncTeamMembers(Request $request, InstitutionTeam $team)
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

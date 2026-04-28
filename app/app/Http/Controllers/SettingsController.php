<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateInstitutionSettingsRequest;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\PasswordPolicy;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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
        if (! ($user instanceof User)) {
            abort(403);
        }

        $isViispFirstPassword = $user->viisp_personal_code_hash !== null
            && $user->viisp_local_password_set_at === null;

        if ($isViispFirstPassword) {
            $rules = [
                'password' => array_merge(['required'], $policy->rules()),
                'password_confirmation' => ['required', 'same:password'],
            ];
        } else {
            $rules = [
                'current_password' => ['required', 'current_password'],
                'password' => array_merge(['required'], $policy->rules()),
                'password_confirmation' => ['required', 'same:password'],
            ];
        }

        $messages = array_merge(
            $policy->validationMessagesForPasswordField(),
            [
                'current_password.required' => 'Įveskite dabartinį slaptažodį.',
                'current_password.current_password' => 'Dabartinis slaptažodis neteisingas.',
            ],
        );

        $validated = $request->validate($rules, $messages);

        if (! $policy->validateProhibitNameEmail($validated['password'], $user->name, $user->email)) {
            return back()->withErrors([
                'password' => 'Slaptažodyje negalima naudoti vardo, pavardės ar el. pašto adreso.',
            ]);
        }

        $updates = [
            'password' => Hash::make($validated['password']),
        ];
        if ($user->viisp_personal_code_hash !== null) {
            $updates['viisp_local_password_set_at'] = now();
        }
        $user->update($updates);

        return back()->with('status', $isViispFirstPassword ? 'Slaptažodis sukurtas. Galite prisijungti ir el. paštu.' : 'Slaptažodis pakeistas.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! ($user instanceof User)) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.Auth::id()],
        ]);

        if ($user->viisp_personal_code_hash !== null) {
            unset($validated['name'], $validated['surname']);
        }

        $user->update($validated);

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

    public function updateInstitution(UpdateInstitutionSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $institutionPayload = [
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
        ];

        $user = Auth::user();
        if (! ($user instanceof User)) {
            abort(403);
        }
        if ($user->institution) {
            $institution = $user->institution;
            $institution->update($institutionPayload);
        } else {
            $institution = Institution::create($institutionPayload);
            $user->update(['institution_id' => $institution->id]);
            $user->setRelation('institution', $institution);
        }

        $this->settingsService->updateInstitutionGisConfig($user, $validated['gis'] ?? []);

        return back()->with('success', 'Įstaigos duomenys atnaujinti.');
    }

    private const PRIVACY_FIELDS = [
        'two_factor_enabled',
        'email_notifications_enabled',
        'email_updates_institution_admin',
        'reporter_email_own_problems',
        'reporter_email_watched_problems',
    ];

    public function updatePrivacy(Request $request): RedirectResponse
    {
        $rules = array_fill_keys(self::PRIVACY_FIELDS, ['sometimes', 'boolean']);
        $validated = $request->validate($rules);

        $data = collect(self::PRIVACY_FIELDS)
            ->filter(fn ($field) => array_key_exists($field, $validated))
            ->mapWithKeys(fn ($field) => [$field => filter_var($validated[$field], FILTER_VALIDATE_BOOLEAN)])
            ->all();

        if ($data !== []) {
            $user = Auth::user();
            if (! ($user instanceof User)) {
                abort(403);
            }
            $user->update($data);
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
        $user = Auth::user();
        if (! ($user instanceof User)) {
            abort(403);
        }
        Auth::logout();
        $user->update(['archived_at' => now()]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

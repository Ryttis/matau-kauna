<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordPolicy;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SettingsApiController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService,
    ) {}

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        if ($user->viisp_personal_code_hash !== null) {
            unset($validated['name'], $validated['surname']);
        } else {
            $validated['surname'] = null;
        }

        $user->update($validated);

        return response()->json([
            'ok' => true,
            'message' => 'Profilis atnaujintas.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'surname' => $user->surname,
                'email' => $user->email,
            ],
        ]);
    }

    public function updateTwoFactor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'two_factor_enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if (! $this->twoFactorService->isStaffUser($user)) {
            throw ValidationException::withMessages([
                'two_factor_enabled' => ['2FA prieinama tik vidiniams vartotojams.'],
            ]);
        }

        $enabled = (bool) $validated['two_factor_enabled'];

        if ($enabled && ! $this->twoFactorService->isGloballyRequired()) {
            throw ValidationException::withMessages([
                'two_factor_enabled' => ['Sistemos administratorius yra išjungęs 2FA visiems vartotojams.'],
            ]);
        }

        $user->update(['two_factor_enabled' => $enabled]);

        return response()->json([
            'ok' => true,
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'two_factor_globally_required' => $this->twoFactorService->isGloballyRequired(),
        ]);
    }

    public function updateEmailNotifications(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reporter_email_own_problems' => ['required', 'boolean'],
            'reporter_email_watched_problems' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'reporter_email_own_problems' => $request->boolean('reporter_email_own_problems'),
            'reporter_email_watched_problems' => $request->boolean('reporter_email_watched_problems'),
        ])->save();

        return response()->json([
            'ok' => true,
            'email_notifications' => [
                'reporter_email_own_problems' => (bool) $user->reporter_email_own_problems,
                'reporter_email_watched_problems' => (bool) $user->reporter_email_watched_problems,
            ],
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $policy = PasswordPolicy::get();
        $isViispFirstPassword = $user->viisp_personal_code_hash !== null
            && $user->viisp_local_password_set_at === null;

        $rules = [
            'password' => array_merge(['required'], $policy->rules()),
            'password_confirmation' => ['required', 'same:password'],
        ];
        if (! $isViispFirstPassword) {
            $rules['current_password'] = ['required', 'current_password'];
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
            throw ValidationException::withMessages([
                'password' => ['Slaptažodyje negalima naudoti vardo, pavardės ar el. pašto adreso.'],
            ]);
        }

        $updates = ['password' => Hash::make($validated['password'])];
        if ($user->viisp_personal_code_hash !== null) {
            $updates['viisp_local_password_set_at'] = now();
        }
        $user->update($updates);

        return response()->json([
            'ok' => true,
            'message' => $isViispFirstPassword
                ? 'Slaptažodis sukurtas. Galite prisijungti ir el. paštu.'
                : 'Slaptažodis pakeistas.',
            'viisp_local_password_set' => $user->viisp_personal_code_hash !== null,
        ]);
    }
}

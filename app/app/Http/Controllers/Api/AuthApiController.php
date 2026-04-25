<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'login_type' => ['required', 'string', 'in:pareiskejas,darbuotojas'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed', [], 'lt')],
            ]);
        }

        if (! $user->canAuthenticateWithEmailPassword()) {
            throw ValidationException::withMessages([
                'email' => ['Prisijunkite per el. valdžios vartus arba nustatykite slaptažodį skiltyje Profilis.'],
            ]);
        }

        $staffRoles = [
            UserRole::ROLE_SYSTEM_ADMIN,
            UserRole::ROLE_PROBLEM_ADMIN,
            UserRole::ROLE_INSTITUTION_ADMIN,
            UserRole::ROLE_MUNICIPALITY_REP,
            UserRole::ROLE_EMPLOYEE,
        ];
        $hasStaffRole = collect($staffRoles)->contains(fn (string $role) => $user->hasRole($role));

        $invalidReporter = $validated['login_type'] === 'pareiskejas'
            && (! $user->hasRole(UserRole::ROLE_REPORTER) || $hasStaffRole);
        $invalidStaff = $validated['login_type'] !== 'pareiskejas' && ! $hasStaffRole;
        if ($invalidReporter || $invalidStaff) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed', [], 'lt')],
            ]);
        }

        $deviceName = $validated['device_name'] ?? 'swagger';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->symbols(),
            ],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->create([
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

        $deviceName = $validated['device_name'] ?? 'swagger';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'surname' => $user->surname,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
            'reporter_report_terms_accepted_at' => $user->reporter_report_terms_accepted_at?->toIso8601String(),
            'reporter_wizard_completed_at' => $user->reporter_wizard_completed_at?->toIso8601String(),
            'roles' => $user->roles->pluck('role')->values()->all(),
            'permissions' => $user->permissions(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    private const TWO_FACTOR_PENDING_TTL_MINUTES = 10;

    public function __construct(
        private TwoFactorService $twoFactorService,
        private AuditLogService $auditLogService,
    ) {}

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

        $hasStaffRole = collect(UserRoleEnum::staffValues())->contains(fn (string $role) => $user->hasAssignedRole($role));

        $invalidReporter = $validated['login_type'] === 'pareiskejas'
            && (! $user->hasAssignedRole(UserRoleEnum::Reporter->value) || $hasStaffRole);
        $invalidStaff = $validated['login_type'] !== 'pareiskejas' && ! $hasStaffRole;
        if ($invalidReporter || $invalidStaff) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed', [], 'lt')],
            ]);
        }

        $deviceName = $validated['device_name'] ?? 'swagger';

        if ($this->twoFactorService->requiresChallenge($user)) {
            $plain = Str::random(64);
            $key = 'api_2fa_pending:'.hash('sha256', $plain);
            Cache::put($key, [
                'user_id' => $user->id,
                'device_name' => $deviceName,
            ], now()->addMinutes(self::TWO_FACTOR_PENDING_TTL_MINUTES));

            $payload = [
                'two_factor_required' => true,
                'login_token' => $plain,
            ];

            if ($user->two_factor_confirmed_at === null) {
                $user->refresh();
                $payload['needs_setup'] = true;
                $payload['setup_secret'] = (string) $user->two_factor_secret;
                $payload['otpauth_url'] = $this->twoFactorService->otpauthUrl($user);
                $payload['setup_qr_image_url'] = $this->twoFactorService->qrImageUrl($user);
            }

            return response()->json($payload);
        }

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

    public function verifyTwoFactorLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login_token' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);

        $key = 'api_2fa_pending:'.hash('sha256', $validated['login_token']);
        $pending = Cache::get($key);
        if (! is_array($pending) || empty($pending['user_id'])) {
            throw ValidationException::withMessages([
                'login_token' => ['Sesija nebegalioja. Prisijunkite iš naujo.'],
            ]);
        }

        $user = User::query()->find((int) $pending['user_id']);
        if (! $user || ! $this->twoFactorService->requiresChallenge($user)) {
            Cache::forget($key);
            throw ValidationException::withMessages([
                'login_token' => ['Sesija nebegalioja. Prisijunkite iš naujo.'],
            ]);
        }

        if (! $this->twoFactorService->verifyCode($user, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => ['Neteisingas 2FA kodas.'],
            ]);
        }

        if ($user->two_factor_confirmed_at === null) {
            $user->update(['two_factor_confirmed_at' => now()]);
        }

        Cache::forget($key);

        $deviceName = is_string($pending['device_name'] ?? null) && $pending['device_name'] !== ''
            ? $pending['device_name']
            : 'swagger';
        $token = $user->createToken($deviceName)->plainTextToken;

        $updates = ['last_login_at' => now(), 'inactive_reporter_reminder_sent_at' => null];
        if ($user->archived_at !== null) {
            $updates['archived_at'] = null;
        }
        $user->update($updates);
        LoginLog::create([
            'user_id' => $user->id,
            'event_type' => LoginLog::EVENT_LOGIN,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        $this->auditLogService->logLogin($user, $request);

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

        $reporterRole = \App\Models\UserRole::firstOrCreate(
            ['role' => UserRoleEnum::Reporter->value],
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

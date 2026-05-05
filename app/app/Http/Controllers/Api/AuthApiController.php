<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditLogResult;
use App\Enums\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\User;
use App\Notifications\PushNotification;
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
            'expires_at' => null,
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
            'expires_at' => null,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        if ($request->filled('viisp_registration_token')) {
            return $this->registerViisp($request);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\s\-]+$/'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->symbols(),
            ],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'surname' => $validated['surname'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
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
            'expires_at' => null,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    private function registerViisp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'viisp_registration_token' => ['required', 'string'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\s\-]+$/'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $cacheKey = ViispApiController::REGISTRATION_CACHE_PREFIX.hash('sha256', $validated['viisp_registration_token']);
        $payload = Cache::get($cacheKey);
        if (! is_array($payload) || ! is_string($payload['hashedCode'] ?? null) || $payload['hashedCode'] === '') {
            throw ValidationException::withMessages([
                'viisp_registration_token' => ['Invalid or expired VIISP registration token.'],
            ]);
        }

        $existingUser = User::query()
            ->where('viisp_personal_code_hash', $payload['hashedCode'])
            ->first();
        $deviceName = $validated['device_name'] ?? ($payload['device_name'] ?? 'mobile');
        $deviceName = is_string($deviceName) && $deviceName !== '' ? $deviceName : 'mobile';

        if ($existingUser) {
            Cache::forget($cacheKey);

            return $this->authTokenResponse($existingUser, $deviceName);
        }

        $email = $validated['email'] ?? ($payload['email'] ?? '');
        $email = is_string($email) ? trim($email) : '';
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || str_ends_with($email, '@viisp.local')) {
            throw ValidationException::withMessages([
                'email' => ['A valid email address is required.'],
            ]);
        }
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered.'],
            ]);
        }

        $firstName = is_string($payload['name'] ?? null) ? trim($payload['name']) : '';
        $lastName = is_string($payload['surname'] ?? null) ? trim($payload['surname']) : '';
        if ($firstName === '' || $lastName === '') {
            throw ValidationException::withMessages([
                'viisp_registration_token' => ['VIISP identity is missing required profile fields.'],
            ]);
        }

        $phone = $validated['phone'] ?? ($payload['phone'] ?? null);
        $phone = is_string($phone) && $phone !== '' ? $phone : null;

        $user = User::query()->create([
            'name' => $firstName,
            'surname' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make(Str::random(64)),
            'viisp_personal_code_hash' => $payload['hashedCode'],
            'terms_accepted_at' => now(),
        ]);

        $reporterRole = \App\Models\UserRole::firstOrCreate(
            ['role' => UserRoleEnum::Reporter->value],
            ['name' => 'PareiÅ¡kÄ—jas']
        );
        $user->roles()->sync([$reporterRole->id]);
        $user->sendEmailVerificationNotification();
        Cache::forget($cacheKey);

        return $this->authTokenResponse($user->fresh(), $deviceName, 201);
    }

    private function authTokenResponse(User $user, string $deviceName, int $status = 200): JsonResponse
    {
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => null,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], $status);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'surname' => $user->surname,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified' => $user->hasVerifiedEmail(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
            'viisp_linked' => $user->viisp_personal_code_hash !== null,
            'can_authenticate_with_email_password' => $user->canAuthenticateWithEmailPassword(),
            'email_notifications_enabled' => (bool) $user->email_notifications_enabled,
            'email_updates_institution_admin' => (bool) $user->email_updates_institution_admin,
            'reporter_email_own_problems' => (bool) $user->reporter_email_own_problems,
            'reporter_email_watched_problems' => (bool) $user->reporter_email_watched_problems,
            'push_notifications_registered' => $user->push_fcm_token !== null,
            'push_device_id' => $user->push_device_id,
            'push_platform' => $user->push_platform,
            'push_app_version' => $user->push_app_version,
            'push_registered_at' => $user->push_registered_at?->toIso8601String(),
            'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
            'reporter_report_terms_accepted_at' => $user->reporter_report_terms_accepted_at?->toIso8601String(),
            'reporter_wizard_completed_at' => $user->reporter_wizard_completed_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'archived_at' => $user->archived_at?->toIso8601String(),
            'institution_id' => $user->institution_id,
            'roles' => $user->roles->pluck('role')->values()->all(),
            'permissions' => $user->permissions(),
        ]);
    }

    public function registerPushDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:255'],
            'fcm_token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'in:ios,android,web'],
            'app_version' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'push_device_id' => $validated['device_id'],
            'push_fcm_token' => $validated['fcm_token'],
            'push_platform' => $validated['platform'] ?? null,
            'push_app_version' => $validated['app_version'] ?? null,
            'push_registered_at' => now(),
        ])->save();

        return response()->json([
            'ok' => true,
            'device' => [
                'device_id' => $user->push_device_id,
                'platform' => $user->push_platform,
                'app_version' => $user->push_app_version,
                'registered_at' => $user->push_registered_at?->toIso8601String(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function sendPushTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:240'],
        ]);

        $user = $request->user();
        if (! $user->push_fcm_token) {
            abort(422, 'Push įrenginys neregistruotas. Pirma iškvieskite POST /api/me/push-device.');
        }

        $user->notify(new PushNotification(
            title: $validated['title'] ?? 'Kauno problemos',
            body: $validated['body'] ?? 'Tai bandomasis pranešimas.',
        ));

        return response()->json(['ok' => true]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $user->tokens()->delete();
            $user->update(['archived_at' => now()]);
            $this->auditLogService->logUserAction($user, 'auth.account.delete', 'User', $user->id, AuditLogResult::Success);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($user, 'auth.account.delete', 'User', $user->id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }
}

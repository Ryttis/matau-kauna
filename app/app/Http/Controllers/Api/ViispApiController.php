<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ViispApiController extends Controller
{
    private const STATE_CACHE_PREFIX = 'viisp_mobile_state:';

    private const EXCHANGE_CACHE_PREFIX = 'viisp_mobile_exchange:';

    public const REGISTRATION_CACHE_PREFIX = 'viisp_mobile_registration:';

    private const STATE_TTL_SECONDS = 600;

    private const EXCHANGE_TTL_SECONDS = 600;

    public const REGISTRATION_TTL_SECONDS = 600;

    public function init(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'redirect_uri' => ['required', 'string', 'max:2048'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $secret = $this->viispExchangeSecret();
        if ($secret === null) {
            return response()->json(['message' => 'VIISP šiuo metu laikinai nepasiekiamas.'], 503);
        }

        $state = Str::random(48);
        Cache::put(
            self::STATE_CACHE_PREFIX.$state,
            [
                'redirect_uri' => $validated['redirect_uri'],
                'device_name' => $validated['device_name'] ?? 'mobile',
            ],
            now()->addSeconds(self::STATE_TTL_SECONDS)
        );

        $gatewayUrl = config('services.viisp.gateway_url');
        $postbackBase = config('services.viisp.gateway_postback_base');
        $callbackUrl = url('/api/viisp/callback?state='.$state);

        $response = Http::timeout(45)->asJson()->post("{$gatewayUrl}/gateway/viisp/login", [
            'postbackUrl' => $postbackBase,
            'callbackUrl' => $callbackUrl,
            'exchangeSecret' => $secret,
        ]);

        $redirectUrl = $response->json('redirectUrl');
        $redirectUrlStr = is_string($redirectUrl) && $redirectUrl !== '' ? $redirectUrl : null;

        if (! $response->successful() || $redirectUrlStr === null) {
            return response()->json([
                'message' => 'VIISP šiuo metu laikinai nepasiekiamas.',
            ], 503);
        }

        return response()->json([
            'state' => $state,
            'redirect_url' => $redirectUrlStr,
        ]);
    }

    public function callback(Request $request): JsonResponse
    {
        $state = (string) $request->query('state', '');
        $token = (string) $request->query('token', '');

        $stateData = Cache::pull(self::STATE_CACHE_PREFIX.$state);
        if (! is_array($stateData) || ! is_string($stateData['redirect_uri'] ?? null) || $token === '') {
            return $this->callbackError('invalid_state', 'Invalid or expired VIISP state.', 422);
        }

        $redirectUri = $stateData['redirect_uri'];
        $deviceName = is_string($stateData['device_name'] ?? null) && $stateData['device_name'] !== ''
            ? $stateData['device_name']
            : 'mobile';
        $secret = $this->viispExchangeSecret();
        if ($secret === null) {
            return $this->callbackError('viisp_unavailable', 'VIISP is temporarily unavailable.', 503, $redirectUri);
        }

        $gatewayUrl = config('services.viisp.gateway_url');
        $response = Http::timeout(45)
            ->withHeaders(['X-Viisp-Exchange' => $secret])
            ->get("{$gatewayUrl}/gateway/viisp/exchange/{$token}");

        $identity = $response->json('identity');
        if (! $response->successful() || ! is_array($identity) || ! is_string($identity['hashedCode'] ?? null) || $identity['hashedCode'] === '') {
            return $this->callbackError('identity_exchange_failed', 'Unable to exchange VIISP identity.', 502, $redirectUri);
        }

        $email = $this->resolveEmail($identity);
        $user = User::query()->where('viisp_personal_code_hash', $identity['hashedCode'])->first();

        if (! $user) {
            $byEmail = User::query()->where('email', $email)->first();
            if ($byEmail) {
                if ($byEmail->viisp_personal_code_hash !== null && $byEmail->viisp_personal_code_hash !== $identity['hashedCode']) {
                    return $this->callbackError('email_linked_other_account', 'Email is linked to another account.', 409, $redirectUri);
                }
                $user = $this->linkViispToExistingUser($byEmail, $identity, $email);
            } else {
                $registrationPayload = $this->registrationPayload($identity, $email, $deviceName);
                if ($registrationPayload === null) {
                    return $this->callbackError('identity_incomplete', 'VIISP identity is missing required profile fields.', 422, $redirectUri);
                }

                $registrationToken = Str::random(64);
                Cache::put(
                    self::REGISTRATION_CACHE_PREFIX.hash('sha256', $registrationToken),
                    $registrationPayload,
                    now()->addSeconds(self::REGISTRATION_TTL_SECONDS)
                );

                return response()->json([
                    'registration_required' => true,
                    'next_step' => 'complete_registration',
                    'registration_token' => $registrationToken,
                    'token_type' => 'VIISPRegistration',
                    'expires_in' => self::REGISTRATION_TTL_SECONDS,
                    'registration_endpoint' => url('/api/register'),
                    'required_fields' => ['viisp_registration_token', 'email'],
                    'user' => [
                        'name' => $registrationPayload['name'],
                        'surname' => $registrationPayload['surname'],
                        'email' => $registrationPayload['email'],
                        'phone' => $registrationPayload['phone'],
                    ],
                ]);
            }
        } else {
            $user = $this->syncViispProfile($user, $identity, $email);
        }

        return $this->authTokenResponse($user, $deviceName);
    }

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:64'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $data = Cache::pull(self::EXCHANGE_CACHE_PREFIX.$validated['code']);
        if (! is_array($data) || ! isset($data['user_id'])) {
            return response()->json(['message' => 'Neteisingas arba pasibaigęs VIISP kodas.'], 422);
        }

        $user = User::query()->find((int) $data['user_id']);
        if (! $user) {
            return response()->json(['message' => 'Naudotojas nerastas.'], 404);
        }

        $deviceName = $validated['device_name'] ?? 'mobile';
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

    private function viispExchangeSecret(): ?string
    {
        $s = config('services.viisp.exchange_secret');

        return is_string($s) && $s !== '' ? $s : null;
    }

    private function callbackError(string $error, string $message, int $status, ?string $redirectUri = null): JsonResponse
    {
        $payload = [
            'message' => $message,
            'error' => $error,
        ];

        if (is_string($redirectUri) && $redirectUri !== '') {
            $payload['redirect_uri'] = $redirectUri;
            $payload['redirect_url'] = $this->appendQuery($redirectUri, ['error' => $error]);
        }

        return response()->json($payload, $status);
    }

    private function appendQuery(string $url, array $params): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($params);
    }

    private function resolveEmail(array $identity): string
    {
        $raw = $identity['email'] ?? null;
        if (is_string($raw) && filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return $raw;
        }

        return 'viisp.'.$identity['hashedCode'].'@viisp.local';
    }

    private function linkViispToExistingUser(User $user, array $identity, string $email): User
    {
        $reporterRole = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_REPORTER],
            ['name' => 'Pareiškėjas']
        );

        if (! $user->hasAssignedRole(UserRole::ROLE_REPORTER)) {
            $user->roles()->syncWithoutDetaching([$reporterRole->id]);
        }

        $user = $this->syncViispProfile($user, $identity, $email);
        if ($user->viisp_local_password_set_at === null) {
            $user->forceFill(['viisp_local_password_set_at' => now()])->save();
        }

        return $user->fresh();
    }

    private function registrationPayload(array $identity, string $email, string $deviceName): ?array
    {
        $hashedCode = is_string($identity['hashedCode'] ?? null) ? $identity['hashedCode'] : '';
        $firstName = is_string($identity['firstName'] ?? null) ? trim($identity['firstName']) : '';
        $lastName = is_string($identity['lastName'] ?? null) ? trim($identity['lastName']) : '';

        if ($hashedCode === '' || $firstName === '' || $lastName === '') {
            return null;
        }

        $phone = is_string($identity['phoneNumber'] ?? null) && $identity['phoneNumber'] !== ''
            ? $identity['phoneNumber']
            : null;

        return [
            'hashedCode' => $hashedCode,
            'name' => $firstName,
            'surname' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'device_name' => $deviceName,
        ];
    }

    private function authTokenResponse(User $user, string $deviceName): JsonResponse
    {
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

    private function syncViispProfile(User $user, array $identity, string $email): User
    {
        $updates = [
            'viisp_personal_code_hash' => $identity['hashedCode'],
        ];

        if (is_string($identity['firstName'] ?? null) && $identity['firstName'] !== '') {
            $updates['name'] = $identity['firstName'];
        }
        if (is_string($identity['lastName'] ?? null) && $identity['lastName'] !== '') {
            $updates['surname'] = $identity['lastName'];
        }
        if (is_string($identity['phoneNumber'] ?? null) && $identity['phoneNumber'] !== '') {
            $updates['phone'] = $identity['phoneNumber'];
        }

        if ($user->email !== $email && str_ends_with($user->email, '@viisp.local') && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $updates['email'] = $email;
        }

        $user->update($updates);

        return $user->fresh();
    }
}

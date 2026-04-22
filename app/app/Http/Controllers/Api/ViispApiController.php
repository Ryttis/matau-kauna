<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ViispApiController extends Controller
{
    private const STATE_CACHE_PREFIX = 'viisp_mobile_state:';

    private const EXCHANGE_CACHE_PREFIX = 'viisp_mobile_exchange:';

    private const STATE_TTL_SECONDS = 600;

    private const EXCHANGE_TTL_SECONDS = 600;

    public function init(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'redirect_uri' => ['required', 'string', 'max:2048'],
        ]);

        $secret = $this->viispExchangeSecret();
        if ($secret === null) {
            return response()->json(['message' => 'VIISP šiuo metu laikinai nepasiekiamas.'], 503);
        }

        $state = Str::random(48);
        Cache::put(
            self::STATE_CACHE_PREFIX.$state,
            ['redirect_uri' => $validated['redirect_uri']],
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

    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');
        $token = (string) $request->query('token', '');

        $stateData = Cache::pull(self::STATE_CACHE_PREFIX.$state);
        if (! is_array($stateData) || ! is_string($stateData['redirect_uri'] ?? null) || $token === '') {
            return redirect()->away($this->fallbackRedirectWithError('invalid_state'));
        }

        $redirectUri = $stateData['redirect_uri'];
        $secret = $this->viispExchangeSecret();
        if ($secret === null) {
            return redirect()->away($this->appendQuery($redirectUri, ['error' => 'viisp_unavailable']));
        }

        $gatewayUrl = config('services.viisp.gateway_url');
        $response = Http::timeout(45)
            ->withHeaders(['X-Viisp-Exchange' => $secret])
            ->get("{$gatewayUrl}/gateway/viisp/exchange/{$token}");

        $identity = $response->json('identity');
        if (! $response->successful() || ! is_array($identity) || ! is_string($identity['hashedCode'] ?? null) || $identity['hashedCode'] === '') {
            return redirect()->away($this->appendQuery($redirectUri, ['error' => 'identity_exchange_failed']));
        }

        $email = $this->resolveEmail($identity);
        $user = User::query()->where('viisp_personal_code_hash', $identity['hashedCode'])->first();

        if (! $user) {
            $byEmail = User::query()->where('email', $email)->first();
            if ($byEmail) {
                if ($byEmail->viisp_personal_code_hash !== null && $byEmail->viisp_personal_code_hash !== $identity['hashedCode']) {
                    return redirect()->away($this->appendQuery($redirectUri, ['error' => 'email_linked_other_account']));
                }
                $user = $this->linkViispToExistingUser($byEmail, $identity, $email);
            } else {
                return redirect()->away($this->appendQuery($redirectUri, ['error' => 'registration_required']));
            }
        } else {
            $user = $this->syncViispProfile($user, $identity, $email);
        }

        $code = Str::random(64);
        Cache::put(
            self::EXCHANGE_CACHE_PREFIX.$code,
            ['user_id' => $user->id],
            now()->addSeconds(self::EXCHANGE_TTL_SECONDS)
        );

        return redirect()->away($this->appendQuery($redirectUri, ['code' => $code]));
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

    private function fallbackRedirectWithError(string $error): string
    {
        return $this->appendQuery(url('/login'), ['error' => $error]);
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

        if (! $user->hasRole(UserRole::ROLE_REPORTER)) {
            $user->roles()->syncWithoutDetaching([$reporterRole->id]);
        }

        $user = $this->syncViispProfile($user, $identity, $email);
        if ($user->viisp_local_password_set_at === null) {
            $user->forceFill(['viisp_local_password_set_at' => now()])->save();
        }

        return $user->fresh();
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

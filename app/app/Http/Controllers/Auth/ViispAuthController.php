<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ViispRegistrationRequest;
use App\Models\LoginLog;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ViispAuthController extends Controller
{
    private const REGISTRATION_SESSION_KEY = 'viisp_registration_payload';

    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function redirectToViisp(Request $request): RedirectResponse
    {
        $exchangeSecret = $this->viispExchangeSecret();
        if ($exchangeSecret === null) {
            return $this->viispLoginError('VIISP: trūksta VIISP_EXCHANGE_SECRET (.env).');
        }

        $gatewayUrl = config('services.viisp.gateway_url');
        $postbackBase = config('services.viisp.gateway_postback_base');

        $response = Http::timeout(45)->asJson()->post("{$gatewayUrl}/gateway/viisp/login", [
            'postbackUrl' => $postbackBase,
            'callbackUrl' => url('/viisp/callback'),
            'exchangeSecret' => $exchangeSecret,
        ]);

        $redirectUrl = $response->json('redirectUrl');
        $redirectUrlStr = is_string($redirectUrl) && $redirectUrl !== '' ? $redirectUrl : null;
        $this->auditViispGatewayLoginInit($request, $response, $postbackBase, $gatewayUrl, $redirectUrlStr);

        if (! $response->successful()) {
            $json = $response->json();
            $detail = '';
            if (is_array($json)) {
                $err = $json['error'] ?? null;
                if (is_string($err)) {
                    $detail = $err;
                } elseif (is_array($err)) {
                    $detail = json_encode($err, JSON_UNESCAPED_UNICODE);
                }
            }
            if ($detail === '') {
                $detail = Str::limit(trim($response->body()), 500);
            }

            return $this->viispLoginError('VIISP gateway '.$response->status().': '.$detail);
        }

        if ($redirectUrlStr === null) {
            return $this->viispLoginError('VIISP: neteisingas gateway atsakymas (nėra redirectUrl).');
        }

        return redirect()->away($redirectUrlStr);
    }

    public function callback(Request $request): RedirectResponse
    {
        $token = $request->query('token');
        if (! is_string($token) || $token === '') {
            return $this->viispLoginError('VIISP: trūksta prisijungimo žymės.');
        }

        $secret = $this->viispExchangeSecret();
        if ($secret === null) {
            return $this->viispLoginError('VIISP: serveryje nesukonfigūruotas VIISP_EXCHANGE_SECRET.');
        }

        $gatewayUrl = config('services.viisp.gateway_url');
        $response = Http::timeout(45)
            ->withHeaders(['X-Viisp-Exchange' => $secret])
            ->get("{$gatewayUrl}/gateway/viisp/exchange/{$token}");

        $identity = $response->json('identity');
        $identityArr = is_array($identity) ? $identity : null;
        $this->auditViispGatewayExchange($request, $response, $gatewayUrl, $token, $identityArr);

        if (! $response->successful()) {
            return $this->viispLoginError('VIISP: nepavyko patvirtinti tapatybės. Bandykite dar kartą.');
        }

        if (! is_array($identity) || empty($identity['hashedCode']) || ! is_string($identity['hashedCode'])) {
            return $this->viispLoginError('VIISP: gauti nepilni tapatybės duomenys.');
        }

        $email = $this->resolveEmail($identity);

        $user = User::query()
            ->where('viisp_personal_code_hash', $identity['hashedCode'])
            ->notArchived()
            ->first();

        if (! $user) {
            $byEmail = User::query()->where('email', $email)->notArchived()->first();
            if ($byEmail) {
                if ($byEmail->viisp_personal_code_hash !== null && $byEmail->viisp_personal_code_hash !== $identity['hashedCode']) {
                    return $this->viispLoginError('Šis el. paštas jau susietas su kita paskyra. Kreipkitės į administratorių.');
                }
                $user = $this->linkViispToExistingUser($byEmail, $identity, $email);
            } else {
                $this->putRegistrationPayload($request, $identity);

                return redirect()->route('viisp.register');
            }
        } else {
            $user = $this->syncViispProfile($user, $identity, $email);
        }

        return $this->finalizeViispLogin($request, $user);
    }

    public function showRegistration(Request $request): Response|RedirectResponse
    {
        $payload = $this->readRegistrationPayloadFromSession($request);
        if ($payload === null) {
            return $this->viispLoginError('Sesija pasibaigė arba registracijos duomenys nebegalioja. Bandykite prisijungti per el. valdžios vartus dar kartą.');
        }

        $prefillEmail = $this->viispProvidedEmailForRegistrationForm($payload);

        return Inertia::render('Auth/ViispCompleteRegistration', [
            'prefill' => [
                'name' => is_string($payload['firstName'] ?? null) ? $payload['firstName'] : '',
                'surname' => is_string($payload['lastName'] ?? null) ? $payload['lastName'] : '',
                'email' => $prefillEmail,
                'phone' => is_string($payload['phoneNumber'] ?? null) ? $payload['phoneNumber'] : '',
            ],
            'companyName' => is_string($payload['companyName'] ?? null) ? $payload['companyName'] : null,
            'isCompany' => ($payload['subjectType'] ?? null) === 'company',
        ]);
    }

    public function storeRegistration(ViispRegistrationRequest $request): RedirectResponse
    {
        $payload = $this->readRegistrationPayloadFromSession($request);
        if ($payload === null) {
            return $this->viispLoginError('Sesija pasibaigė arba registracijos duomenys nebegalioja. Bandykite prisijungti per el. valdžios vartus dar kartą.');
        }

        $hashedCode = is_string($payload['hashedCode'] ?? null) ? $payload['hashedCode'] : '';
        if ($hashedCode === '') {
            $request->session()->forget(self::REGISTRATION_SESSION_KEY);

            return $this->viispLoginError('VIISP: neteisingi registracijos duomenys.');
        }

        if (User::query()->where('viisp_personal_code_hash', $hashedCode)->exists()) {
            $request->session()->forget(self::REGISTRATION_SESSION_KEY);

            return $this->viispLoginError('Paskyra su šia tapatybe jau egzistuoja. Prisijunkite per el. valdžios vartus.');
        }

        $validated = $request->validated();

        $reporterRole = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_REPORTER],
            ['name' => 'Pareiškėjas']
        );

        $phone = isset($validated['phone']) && is_string($validated['phone']) && $validated['phone'] !== ''
            ? $validated['phone']
            : null;

        $firstName = is_string($payload['firstName'] ?? null) ? trim($payload['firstName']) : '';
        $lastName = is_string($payload['lastName'] ?? null) ? trim($payload['lastName']) : '';
        if ($firstName === '' || $lastName === '') {
            $request->session()->forget(self::REGISTRATION_SESSION_KEY);

            return $this->viispLoginError('VIISP: gauti nepilni tapatybės duomenys (trūksta vardo ar pavardės).');
        }

        $user = User::create([
            'name' => $firstName,
            'surname' => $lastName,
            'email' => $validated['email'],
            'phone' => $phone,
            'password' => Hash::make(Str::random(64)),
            'viisp_personal_code_hash' => $hashedCode,
        ]);
        $user->roles()->sync([$reporterRole->id]);
        $user->sendEmailVerificationNotification();

        $request->session()->forget(self::REGISTRATION_SESSION_KEY);

        return $this->finalizeViispLogin($request, $user);
    }

    private function finalizeViispLogin(Request $request, User $user): RedirectResponse
    {
        Auth::login($user, false);
        $request->session()->regenerate();

        $user->update(['last_login_at' => now(), 'inactive_reporter_reminder_sent_at' => null]);
        LoginLog::create([
            'user_id' => $user->id,
            'event_type' => LoginLog::EVENT_LOGIN,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        $this->auditLogService->logLogin($user, $request);

        $roles = $user->roles()->get();
        if ($roles->count() > 1) {
            return redirect()->route('role.select');
        }
        if ($roles->count() === 1) {
            $request->session()->put('active_role_id', $roles->first()->id);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function putRegistrationPayload(Request $request, array $identity): void
    {
        $payload = [
            'hashedCode' => $identity['hashedCode'],
            'firstName' => is_string($identity['firstName'] ?? null) ? $identity['firstName'] : '',
            'lastName' => is_string($identity['lastName'] ?? null) ? $identity['lastName'] : '',
            'email' => $identity['email'] ?? null,
            'phoneNumber' => $identity['phoneNumber'] ?? null,
            'companyName' => $identity['companyName'] ?? null,
            'subjectType' => is_string($identity['subjectType'] ?? null) ? $identity['subjectType'] : 'person',
        ];
        $request->session()->put(
            self::REGISTRATION_SESSION_KEY,
            Crypt::encryptString(json_encode($payload, JSON_UNESCAPED_UNICODE))
        );
    }

    private function readRegistrationPayloadFromSession(Request $request): ?array
    {
        $cipher = $request->session()->get(self::REGISTRATION_SESSION_KEY);
        if (! is_string($cipher) || $cipher === '') {
            return null;
        }
        try {
            $json = Crypt::decryptString($cipher);
            $data = json_decode($json, true);
            if (! is_array($data) || empty($data['hashedCode']) || ! is_string($data['hashedCode'])) {
                return null;
            }

            return $data;
        } catch (\Throwable) {
            return null;
        }
    }

    private function viispProvidedEmailForRegistrationForm(array $payload): string
    {
        $raw = $payload['email'] ?? null;
        if (! is_string($raw)) {
            return '';
        }
        $trim = trim($raw);
        if ($trim === '' || ! filter_var($trim, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        if (str_ends_with(strtolower($trim), '@viisp.local')) {
            return '';
        }

        return $trim;
    }

    private function viispExchangeSecret(): ?string
    {
        $s = config('services.viisp.exchange_secret');

        return is_string($s) && $s !== '' ? $s : null;
    }

    private function auditViispGatewayLoginInit(
        Request $request,
        HttpClientResponse $response,
        string $postbackBase,
        string $gatewayUrl,
        ?string $redirectUrl,
    ): void {
        $ok = $response->successful() && $redirectUrl !== null;
        $message = null;
        if (! $response->successful()) {
            $message = Str::limit(trim($response->body()), 500);
        } elseif ($redirectUrl === null) {
            $message = 'Atsakyme nėra redirectUrl.';
        }

        $this->auditLogService->logIntegration(
            'viisp.login_init',
            'VIISP (integracinis gateway)',
            [
                'from_system' => (string) config('app.name'),
                'to_system' => 'VIISP integracijos gateway',
                'occurred_note' => 'Siuntimas: prisijungimo užklausa į gateway',
                'client_ip' => $request->ip(),
                'http' => [
                    'method' => 'POST',
                    'path' => '/gateway/viisp/login',
                    'base_url' => rtrim($gatewayUrl, '/'),
                ],
                'request_params' => [
                    'postbackUrl' => $postbackBase,
                    'callbackUrl' => url('/viisp/callback'),
                    'exchangeSecret' => '[užslpta]',
                ],
                'response' => [
                    'http_status' => $response->status(),
                    'redirect_received' => $redirectUrl !== null,
                ],
            ],
            $ok ? AuditLogResult::Success : AuditLogResult::Error,
            $message,
        );
    }

    private function auditViispGatewayExchange(
        Request $request,
        HttpClientResponse $response,
        string $gatewayUrl,
        string $token,
        ?array $identity,
    ): void {
        $hasIdentity = is_array($identity)
            && ! empty($identity['hashedCode'])
            && is_string($identity['hashedCode']);
        $httpOk = $response->successful();
        $ok = $httpOk && $hasIdentity;

        $message = null;
        if (! $httpOk) {
            $message = Str::limit(trim($response->body()), 500);
        } elseif (! $hasIdentity) {
            $message = 'HTTP sėkmė, bet trūksta identity / hashedCode.';
        }

        $this->auditLogService->logIntegration(
            'viisp.identity_exchange',
            'VIISP (integracinis gateway)',
            [
                'from_system' => 'VIISP integracijos gateway',
                'to_system' => (string) config('app.name'),
                'occurred_note' => 'Gavimas: tapatybės mainas (exchange)',
                'client_ip' => $request->ip(),
                'http' => [
                    'method' => 'GET',
                    'path' => '/gateway/viisp/exchange/{token}',
                    'base_url' => rtrim($gatewayUrl, '/'),
                ],
                'request_params' => [
                    'token' => '[užslpta, ilgis: '.strlen($token).']',
                    'header_X-Viisp-Exchange' => '[užslpta]',
                ],
                'response' => [
                    'http_status' => $response->status(),
                    'identity_field_keys' => is_array($identity) ? array_keys($identity) : [],
                    'has_hashed_code' => $hasIdentity,
                ],
            ],
            $ok ? AuditLogResult::Success : AuditLogResult::Error,
            $message,
        );
    }

    private function viispLoginError(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'email' => $message,
        ]);
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

        return $this->syncViispProfile($user, $identity, $email);
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

        $needsEmailVerification = false;
        if ($user->email !== $email) {
            if (str_ends_with($user->email, '@viisp.local') && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $updates['email'] = $email;
                if (! str_ends_with($email, '@viisp.local')) {
                    $needsEmailVerification = true;
                }
            }
        }

        $user->update($updates);
        if ($needsEmailVerification) {
            $user->forceFill(['email_verified_at' => null])->save();
            $user->sendEmailVerificationNotification();
        }

        return $user->fresh();
    }
}

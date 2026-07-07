<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Log;
use Mockery;
use PragmaRX\Google2FALaravel\Google2FA;
use Tests\TestCase;

class TwoFactorServiceTest extends TestCase
{
    public function test_verify_code_accepts_configured_totp_window(): void
    {
        $google2FA = $this->google2FAWithTimestamp(1_000_000);
        $service = new TwoFactorService($google2FA);
        $user = $this->userWithTwoFactorSecret('JBSWY3DPEHPK3PXP');
        $codeThreeStepsAhead = $google2FA->oathTotp((string) $user->two_factor_secret, 1_000_003);

        config(['google2fa.window' => 3]);

        $this->assertTrue($service->verifyCode($user, $codeThreeStepsAhead));
    }

    public function test_verify_code_rejects_code_outside_configured_totp_window(): void
    {
        Log::spy();

        $google2FA = $this->google2FAWithTimestamp(1_000_000);
        $service = new TwoFactorService($google2FA);
        $user = $this->userWithTwoFactorSecret('JBSWY3DPEHPK3PXP');
        $codeThreeStepsAhead = $google2FA->oathTotp((string) $user->two_factor_secret, 1_000_003);

        config(['google2fa.window' => 2]);

        $this->assertFalse($service->verifyCode($user, $codeThreeStepsAhead));
    }

    public function test_verify_code_rejects_missing_secret(): void
    {
        Log::spy();

        $service = new TwoFactorService($this->google2FAWithTimestamp(1_000_000));

        $this->assertFalse($service->verifyCode(new User, '123456'));
        Log::shouldNotHaveReceived('warning');
    }

    public function test_verify_code_logs_safe_diagnostics_for_code_inside_diagnostic_window(): void
    {
        Log::spy();

        $google2FA = $this->google2FAWithTimestamp(1_000_000);
        $service = new TwoFactorService($google2FA);
        $user = $this->userWithTwoFactorSecret('JBSWY3DPEHPK3PXP');
        $user->id = 123;
        $codeFourStepsAhead = $google2FA->oathTotp((string) $user->two_factor_secret, 1_000_004);

        config([
            'google2fa.window' => 3,
            'google2fa.diagnostic_window' => 6,
        ]);

        $this->assertFalse($service->verifyCode($user, $codeFourStepsAhead));

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('2FA verification failed', Mockery::on(function (array $context): bool {
                $serialized = json_encode($context);

                return $context['user_id'] === 123
                    && $context['configured_window'] === 3
                    && $context['diagnostic_window'] === 6
                    && $context['server_totp_step'] === 1_000_000
                    && $context['matched_in_diagnostic_window'] === true
                    && $context['matched_offset'] === 4
                    && ! str_contains($serialized, '123456')
                    && ! str_contains($serialized, 'JBSWY3DPEHPK3PXP')
                    && ! array_key_exists('code', $context)
                    && ! array_key_exists('secret', $context);
            }));
    }

    private function google2FAWithTimestamp(int $timestamp): Google2FA
    {
        return new class(request(), $timestamp) extends Google2FA
        {
            public function __construct($request, private int $testTimestamp)
            {
                parent::__construct($request);
            }

            public function getTimestamp()
            {
                return $this->testTimestamp;
            }
        };
    }

    private function userWithTwoFactorSecret(string $secret): User
    {
        $user = new User;
        $user->two_factor_secret = $secret;

        return $user;
    }
}

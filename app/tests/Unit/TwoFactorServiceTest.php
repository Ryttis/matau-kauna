<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\TwoFactorService;
use PragmaRX\Google2FALaravel\Google2FA;
use Tests\TestCase;

class TwoFactorServiceTest extends TestCase
{
    public function test_verify_code_accepts_configured_totp_window(): void
    {
        $google2FA = $this->google2FAWithTimestamp(1_000_000);
        $service = new TwoFactorService($google2FA);
        $user = $this->userWithTwoFactorSecret('JBSWY3DPEHPK3PXP');
        $codeTwoStepsAhead = $google2FA->oathTotp((string) $user->two_factor_secret, 1_000_002);

        config(['google2fa.window' => 2]);

        $this->assertTrue($service->verifyCode($user, $codeTwoStepsAhead));
    }

    public function test_verify_code_rejects_code_outside_configured_totp_window(): void
    {
        $google2FA = $this->google2FAWithTimestamp(1_000_000);
        $service = new TwoFactorService($google2FA);
        $user = $this->userWithTwoFactorSecret('JBSWY3DPEHPK3PXP');
        $codeTwoStepsAhead = $google2FA->oathTotp((string) $user->two_factor_secret, 1_000_002);

        config(['google2fa.window' => 1]);

        $this->assertFalse($service->verifyCode($user, $codeTwoStepsAhead));
    }

    public function test_verify_code_rejects_missing_secret(): void
    {
        $service = new TwoFactorService($this->google2FAWithTimestamp(1_000_000));

        $this->assertFalse($service->verifyCode(new User, '123456'));
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

<?php

return [
    /*
     * Enable / disable Google2FA.
     */
    'enabled' => env('OTP_ENABLED', true),

    /*
     * Lifetime in minutes.
     *
     * 0 = eternal.
     */
    'lifetime' => env('OTP_LIFETIME', 0),

    /*
     * Renew lifetime at every new request.
     */
    'keep_alive' => env('OTP_KEEP_ALIVE', true),

    /*
     * Auth container binding.
     */
    'auth' => 'auth',

    /*
     * Guard.
     */
    'guard' => '',

    /*
     * 2FA verified session var.
     */
    'session_var' => 'google2fa',

    /*
     * One Time Password request input name.
     */
    'otp_input' => 'one_time_password',

    /*
     * One Time Password validation window in 30-second TOTP steps.
     */
    'window' => (int) env('OTP_WINDOW', 3),

    /*
     * Wider window used only to diagnose failed OTP attempts.
     */
    'diagnostic_window' => (int) env('OTP_DIAGNOSTIC_WINDOW', 6),

    /*
     * Forbid user to reuse One Time Passwords.
     */
    'forbid_old_passwords' => false,

    /*
     * User's table column for google2fa secret.
     */
    'otp_secret_column' => 'google2fa_secret',

    /*
     * One Time Password View.
     */
    'view' => 'google2fa.index',

    /*
     * One Time Password error message.
     */
    'error_messages' => [
        'wrong_otp' => "The 'One Time Password' typed was wrong.",
        'cannot_be_empty' => 'One Time Password cannot be empty.',
        'unknown' => 'An unknown error has occurred. Please try again.',
    ],

    /*
     * Throw exceptions or just fire events?
     */
    'throw_exceptions' => env('OTP_THROW_EXCEPTION', true),

    /*
     * Which image backend to use for generating QR codes?
     */
    'qrcode_image_backend' => \PragmaRX\Google2FALaravel\Support\Constants::QRCODE_IMAGE_BACKEND_SVG,
];

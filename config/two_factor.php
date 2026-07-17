<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enforced (master switch)
    |--------------------------------------------------------------------------
    |
    | When false, two-factor authentication is turned off entirely and login is
    | a simple email + password — intended for local/dev testing so you don't
    | have to wait on an OTP email. Set TWO_FACTOR_ENFORCED=false in your .env.
    |
    | Defaults to TRUE (secure). Leave it on in any environment that holds real
    | data. Per-user/role gating still applies on top of this when enforced.
    |
    */

    'enforced' => filter_var(env('TWO_FACTOR_ENFORCED', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Per-account exemptions (testing only)
    |--------------------------------------------------------------------------
    |
    | Emails listed here skip 2FA even while it is enforced for everyone else.
    | This is a narrow escape hatch for driving automated tests against an
    | environment whose OTP email delivery is unavailable — it keeps 2FA on for
    | every real account and only exempts the throwaway test logins listed.
    |
    | Add addresses via TWO_FACTOR_EXEMPT_EMAILS (comma-separated) in .env, or
    | the committed defaults below. Remove them once testing is done.
    |
    */

    'exempt_emails' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('TWO_FACTOR_EXEMPT_EMAILS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | OTP Expiry (minutes)
    |--------------------------------------------------------------------------
    */

    'otp_expiry_minutes' => (int) env('TWO_FACTOR_OTP_EXPIRY', 10),

    /*
    |--------------------------------------------------------------------------
    | Maximum verification attempts before lockout
    |--------------------------------------------------------------------------
    */

    'max_attempts' => (int) env('TWO_FACTOR_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Lockout duration after max attempts (minutes)
    |--------------------------------------------------------------------------
    */

    'lockout_minutes' => (int) env('TWO_FACTOR_LOCKOUT_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Resend throttling
    |--------------------------------------------------------------------------
    */

    'resend_cooldown_seconds' => (int) env('TWO_FACTOR_RESEND_COOLDOWN', 60),

    'resend_max_per_hour' => (int) env('TWO_FACTOR_RESEND_MAX_PER_HOUR', 5),

];

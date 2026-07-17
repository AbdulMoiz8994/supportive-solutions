<?php

return [
    'grace_days' => (int) env('PAYROLL_GRACE_DAYS', 10),

    'stub_retention_years' => (int) env('PAYROLL_STUB_RETENTION_YEARS', 7),

    'batch' => [
        'build_day' => 'first_tuesday',
        'pay_day'   => 'following_friday',
    ],

    'period_cutoff_day' => (int) env('PAYROLL_PERIOD_CUTOFF_DAY', 5),

    'accountants_world_url' => env('ACCOUNTANTSWORLD_URL', 'https://www.accountantsworld.com'),
    'accountants_world_api_url' => env('ACCOUNTANTSWORLD_API_URL', 'https://dev-api.payrollrelief.com/integration'),
    'accountants_world_app_id' => env('ACCOUNTANTSWORLD_APP_ID'),
    'accountants_world_api_key' => env('ACCOUNTANTSWORLD_API_KEY'),
    'accountants_world_auth_mode' => env('ACCOUNTANTSWORLD_AUTH_MODE', 'api_key'),
    'accountants_world_timeout' => (int) env('ACCOUNTANTSWORLD_TIMEOUT', 30),
    'accountants_world_pay_schedule_id' => env('ACCOUNTANTSWORLD_PAY_SCHEDULE_ID'),
    'accountants_world_default_pay_type_code' => env('ACCOUNTANTSWORLD_DEFAULT_PAY_TYPE_CODE', 'REG'),
    'accountants_world_oauth_token_url' => env('ACCOUNTANTSWORLD_OAUTH_TOKEN_URL', 'https://dev-auth.accountantsoffice.com/connect/token'),
    'accountants_world_oauth_scope' => env('ACCOUNTANTSWORLD_OAUTH_SCOPE', 'payroll_api'),
    'accountants_world_oauth_client_id' => env('ACCOUNTANTSWORLD_OAUTH_CLIENT_ID'),
    'accountants_world_oauth_client_secret' => env('ACCOUNTANTSWORLD_OAUTH_CLIENT_SECRET'),
    'accountants_world_token_cache_seconds' => (int) env('ACCOUNTANTSWORLD_TOKEN_CACHE_SECONDS', 3000),
    'accountant_email' => env('PAYROLL_ACCOUNTANT_EMAIL'),
    'quickbooks_url'        => env('QUICKBOOKS_PAYROLL_URL'),
    'gusto_url'             => env('GUSTO_PAYROLL_URL'),

    'wage' => [
        'default_hourly' => (float) env('PAYROLL_DEFAULT_HOURLY', 15.00),
        'min_hourly'     => (float) env('PAYROLL_MIN_HOURLY', 7.25),
        'max_hourly'     => (float) env('PAYROLL_MAX_HOURLY', 100.00),
    ],

    /*
    | Estimated withholding rates used to present a gross → net breakdown on the
    | mobile paystub detail. Only FICA (7.65%) is statutory and always correct;
    | federal/state default to 0 until the org configures real rates, and the
    | mobile paystub flags the breakdown as estimated. The authoritative net
    | pay still comes from the payroll provider (Gusto/QuickBooks).
    */
    'tax_estimate' => [
        'enabled' => (bool) env('PAYROLL_TAX_ESTIMATE', true),
        'fica'    => (float) env('PAYROLL_TAX_FICA', 0.0765),
        'federal' => (float) env('PAYROLL_TAX_FEDERAL', 0.0),
        'state'   => (float) env('PAYROLL_TAX_STATE', 0.0),
    ],

    'storage_stub_prefix' => 'payroll/stubs',

    'statuses' => [
        'awaiting_form' => 'Awaiting form',
        'pending'       => 'Pending',
        'ready'         => 'Ready',
        'in_grace'      => 'In grace',
        'late_rolled'   => 'Late - rolled',
        'held'          => 'Held - review',
        'paid'          => 'Paid',
    ],

    'caregiver_types' => [
        'family' => 'Family caregiver',
        'agency' => 'Agency-sourced',
    ],
];

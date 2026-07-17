<?php

$environment = env('HHA_ENVIRONMENT', 'implementation');

$bases = [
    'implementation' => 'https://implementation.hhaexchange.com',
    'production' => 'https://cloud.hhaexchange.com',
];

$base = $bases[$environment] ?? $bases['implementation'];

return [
    'environment' => $environment,

    'bases' => $bases,

    'api_url' => env('HHA_API_URL', $base),
    'token_url' => env('HHA_TOKEN_URL', $base.'/identity/connect/token'),
    'client_id' => env('HHA_CLIENT_ID'),
    'client_secret' => env('HHA_CLIENT_SECRET'),
    'scope' => env('HHA_SCOPE', 'write:aggregator'),
    'attestation_status' => env('HHA_ATTESTATION_STATUS', 'pending'),
    'provider_tax_id' => env('HHA_PROVIDER_TAX_ID'),
    'office_npi' => env('HHA_OFFICE_NPI'),
    'payer_id' => env('HHA_PAYER_ID'),
    'token_cache_seconds' => (int) env('HHA_TOKEN_CACHE_SECONDS', 1500),
    'timeout' => (int) env('HHA_TIMEOUT', 30),

    'endpoints' => [
        'visits' => env('HHA_VISITS_PATH', '/api/v2/visits'),
        'caregivers' => env('HHA_CAREGIVERS_PATH', '/api/v2/caregivers'),
        'transactions' => env('HHA_TRANSACTIONS_PATH', '/api/v2/visits/transactions'),
    ],

    /*
    | Hard ceiling for a single visit's duration. Anything above this (e.g. a
    | 30-hour "visit" caused by a missing clock-out) is auto-flagged "Needs
    | review" and excluded from billing & payroll hour sums until corrected.
    */
    'max_visit_hours' => (float) env('EVV_MAX_VISIT_HOURS', 16),

    /*
    | Default values required by the MI Aggregator Spec when local data is absent.
    */
    'defaults' => [
        'ssn' => '999999999',
        'professional_license_number' => '999999999999',
        'hire_date' => '1900-01-02',
        'gender' => 'Other',
        'caregiver_type' => 'Both',
        'timezone' => 'US/Eastern',
        'call_type' => 'Mobile',
    ],
];

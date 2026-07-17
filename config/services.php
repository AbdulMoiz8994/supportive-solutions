<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'availity' => [
        'env' => env('AVAILITY_ENV', 'demo'),
        'app_id' => env('AVAILITY_APP_ID'),
        'demo_key' => env('AVAILITY_DEMO_KEY'),
        'demo_secret' => env('AVAILITY_DEMO_SECRET'),
        'prod_key' => env('AVAILITY_PROD_KEY'),
        'prod_secret' => env('AVAILITY_PROD_SECRET'),
        'token_url' => env('AVAILITY_TOKEN_URL', 'https://api.availity.com/v1/token'),
        'api_base_url' => env('AVAILITY_API_BASE_URL', 'https://api.availity.com/availity/v1'),
        'base_url_demo' => env('AVAILITY_BASE_URL_DEMO', 'https://api.availity.com/availity/v1'),
        'base_url_prod' => env('AVAILITY_BASE_URL_PROD', 'https://api.availity.com/availity/v1'),
        'scope_demo' => env('AVAILITY_SCOPE_DEMO', 'healthcare-hipaa-transactions-demo healthcare-hipaa-transactions-demo-demo'),
        'scope_prod' => env('AVAILITY_SCOPE_PROD', 'healthcare-hipaa-transactions'),
        'request_type_code' => env('AVAILITY_REQUEST_TYPE', 'PRE_DETERMINATION'),
        'default_payer_id' => env('AVAILITY_DEFAULT_PAYER_ID', 'BCBSF'),
        'default_diagnosis_code' => env('AVAILITY_DEFAULT_DIAGNOSIS_CODE', 'Z74.8'),
        'place_of_service_code' => env('AVAILITY_PLACE_OF_SERVICE', '12'),
        'patient_relationship_code' => env('AVAILITY_PATIENT_RELATIONSHIP', '18'),
        'submitter_id' => env('AVAILITY_SUBMITTER_ID'),
        'mock_scenario_id' => env('AVAILITY_MOCK_SCENARIO_ID'),
        'endpoints' => [
            'professional_claims' => env('AVAILITY_PROFESSIONAL_CLAIMS_PATH', '/professional-claims'),
            'claim_statuses' => env('AVAILITY_CLAIM_STATUSES_PATH', '/claim-statuses'),
            'configurations' => env('AVAILITY_CONFIGURATIONS_PATH', '/configurations'),
            'coverages' => env('AVAILITY_COVERAGES_PATH', '/coverages'),
        ],
        'token_cache_seconds' => (int) env('AVAILITY_TOKEN_CACHE_SECONDS', 240),
        'timeout' => (int) env('AVAILITY_TIMEOUT', 30),
        'http_retries' => (int) env('AVAILITY_HTTP_RETRIES', 2),
        'http_retry_delay' => (int) env('AVAILITY_HTTP_RETRY_DELAY', 200),
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 60),
        'retries' => (int) env('ANTHROPIC_RETRIES', 1),
    ],

];

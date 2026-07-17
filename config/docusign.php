<?php

return [
    'integration_key' => env('DOCUSIGN_INTEGRATION_KEY'),
    'account_id' => env('DOCUSIGN_ACCOUNT_ID'),
    // Impersonated user GUID (required for JWT Grant).
    'user_id' => env('DOCUSIGN_USER_ID'),
    // RSA private key PEM for JWT assertion (or path via DOCUSIGN_PRIVATE_KEY_PATH).
    'private_key' => env('DOCUSIGN_PRIVATE_KEY'),
    'private_key_path' => env('DOCUSIGN_PRIVATE_KEY_PATH'),
    'base_url' => env('DOCUSIGN_BASE_URL', 'https://demo.docusign.net'),
    'oauth_host' => env('DOCUSIGN_OAUTH_HOST', 'account-d.docusign.com'),
    'timeout' => (int) env('DOCUSIGN_TIMEOUT', 20),
];

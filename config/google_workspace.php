<?php

return [
    'client_id' => env('GOOGLE_WORKSPACE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_WORKSPACE_CLIENT_SECRET'),
    'refresh_token' => env('GOOGLE_WORKSPACE_REFRESH_TOKEN'),
    'delegated_user' => env('GOOGLE_WORKSPACE_DELEGATED_USER'),
    'timeout' => (int) env('GOOGLE_WORKSPACE_TIMEOUT', 30),
];

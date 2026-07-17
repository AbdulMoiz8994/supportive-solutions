<?php

return [
    'api_key' => env('RETELL_API_KEY'),
    'agent_id' => env('RETELL_AGENT_ID'),
    'from_number' => env('RETELL_FROM_NUMBER'),
    'webhook_secret' => env('RETELL_WEBHOOK_SECRET'),
    'timeout' => (int) env('RETELL_TIMEOUT', 30),
];

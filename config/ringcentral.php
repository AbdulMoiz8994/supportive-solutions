<?php

return [
  'client_id' => env('RINGCENTRAL_CLIENT_ID'),
  'client_secret' => env('RINGCENTRAL_CLIENT_SECRET'),
  'server_url' => env('RINGCENTRAL_SERVER_URL', 'https://platform.ringcentral.com'),
  'jwt' => env('RINGCENTRAL_JWT'),
  'extension' => env('RINGCENTRAL_EXTENSION'),
  'from_number' => env('RINGCENTRAL_FROM_NUMBER'),
  'timeout' => (int) env('RINGCENTRAL_TIMEOUT', 30),
];

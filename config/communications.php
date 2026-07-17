<?php

return [
  'channels' => [
    'email' => env('COMMUNICATION_EMAIL_DRIVER', 'fake'),
    'fax' => env('COMMUNICATION_FAX_DRIVER', 'fake'),
    'sms' => env('COMMUNICATION_SMS_DRIVER', 'fake'),
  ],

  'queue_owner_label' => env('COMMUNICATIONS_QUEUE_OWNER', 'Ali'),

  'inbound' => [
    'webhook_secret' => env('COMMUNICATIONS_WEBHOOK_SECRET'),
    'organization_id' => env('COMMUNICATIONS_INBOUND_ORGANIZATION_ID'),
  ],

  'ai' => [
    'driver' => env('COMMUNICATIONS_AI_DRIVER', 'local'),
    'openai_api_key' => env('OPENAI_API_KEY'),
    'openai_model' => env('COMMUNICATIONS_AI_MODEL', 'gpt-4o-mini'),
    'timeout' => (int) env('COMMUNICATIONS_AI_TIMEOUT', 20),
  ],

  'sync' => [
    'google_inbound_limit' => (int) env('COMMUNICATIONS_GOOGLE_INBOUND_LIMIT', 25),
    'ringcentral_call_limit' => (int) env('COMMUNICATIONS_RINGCENTRAL_CALL_LIMIT', 25),
    'ringcentral_message_limit' => (int) env('COMMUNICATIONS_RINGCENTRAL_MESSAGE_LIMIT', 25),
  ],

  // Per-sender send throttle (protects against runaway/duplicate sends). A
  // full billing cycle submits every eligible claim for one organization
  // back-to-back under a single automation actor, so the default here must
  // comfortably clear that batch size or legitimate cycles get throttled
  // (see DhsHomeHelpSubmissionService "Failed to email ASW" wrapping).
  'send_rate_limit' => [
    'max_attempts' => (int) env('COMMUNICATION_SEND_RATE_LIMIT_MAX_ATTEMPTS', 200),
    'decay_seconds' => (int) env('COMMUNICATION_SEND_RATE_LIMIT_DECAY_SECONDS', 60),
  ],

  'allowed_attachment_mimes' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'txt'],

  'max_attachment_kilobytes' => env('COMMUNICATION_MAX_ATTACHMENT_KB', 10240),

  'template_variables' => [
    'client.first_name',
    'client.last_name',
    'client.member_id',
    'client.pcp_name',
    'case_coordinator.name',
    'employee.name',
    'agency.name',
  ],
];

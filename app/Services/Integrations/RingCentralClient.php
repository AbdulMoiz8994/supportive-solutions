<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RingCentralClient
{
  protected ?string $lastError = null;

  public function isConfigured(): bool
  {
    return filled(config('ringcentral.client_id'))
      && (filled(config('ringcentral.client_secret')) || filled(config('ringcentral.jwt')));
  }

  public function lastError(): ?string
  {
    return $this->lastError;
  }

  /**
   * @return array{success: bool, message: string}
   */
  public function testConnection(): array
  {
    if (! $this->isConfigured()) {
      return [
        'success' => false,
        'message' => 'RingCentral client ID and secret (or JWT) are required.',
      ];
    }

    $token = $this->accessToken();

    if (! $token) {
      return [
        'success' => false,
        'message' => $this->lastError ?? 'RingCentral authentication failed.',
      ];
    }

    $response = $this->authenticatedRequest($token)->get('/restapi/v1.0/account/~/extension/~');

    if (! $response->successful()) {
      return [
        'success' => false,
        'message' => 'RingCentral API reachable but extension lookup failed: '.$response->body(),
      ];
    }

    return [
      'success' => true,
      'message' => 'RingCentral connected — extension '.$response->json('extensionNumber', 'OK').' verified.',
    ];
  }

  /**
   * @return array{success: bool, provider_message_id: ?string, failure_reason: ?string}
   */
  public function resolveFromNumber(?string $token = null): ?string
  {
    $configured = trim((string) config('ringcentral.from_number'));

    if ($configured !== '') {
      return $configured;
    }

    $token = $token ?? $this->accessToken();

    if (! $token) {
      return null;
    }

    $cacheKey = 'ringcentral.extension_from_number.'.md5((string) config('ringcentral.client_id'));

    return Cache::remember($cacheKey, 3600, function () use ($token) {
      $response = $this->authenticatedRequest($token)
        ->get('/restapi/v1.0/account/~/extension/~/phone-number');

      if (! $response->successful()) {
        $this->lastError = $this->parseError($response);

        return null;
      }

      foreach ($response->json('records', []) as $record) {
        if (! is_array($record)) {
          continue;
        }

        $phone = trim((string) ($record['phoneNumber'] ?? ''));

        if ($phone === '') {
          continue;
        }

        $features = $record['features'] ?? [];

        if (! is_array($features)) {
          $features = [];
        }

        if ($features === [] || in_array('SmsSender', $features, true) || in_array('Sms', $features, true)) {
          return $phone;
        }
      }

      $fallback = trim((string) ($response->json('records.0.phoneNumber') ?? ''));

      return $fallback !== '' ? $fallback : null;
    });
  }

  public function hasScopePermission(string $permission): bool
  {
    $scope = $this->accessTokenScope();

    if ($scope === '') {
      return false;
    }

    foreach (preg_split('/\s+/', $scope) ?: [] as $entry) {
      if (strcasecmp($entry, $permission) === 0) {
        return true;
      }
    }

    return false;
  }

  public function accessTokenScope(): string
  {
    $payload = $this->requestAccessToken();

    if ($payload === null) {
      return '';
    }

    return (string) ($payload['scope'] ?? '');
  }

  /**
   * @return array{success: bool, message: string}
   */
  public function testSmsSendPermission(?string $token = null): array
  {
    $token = $token ?? $this->accessToken();

    if (! $token) {
      return [
        'success' => false,
        'message' => $this->lastError ?? 'RingCentral authentication failed.',
      ];
    }

    if ($this->hasScopePermission('SMS')) {
      return [
        'success' => true,
        'message' => 'SMS API permission verified on access token.',
      ];
    }

    $response = $this->authenticatedRequest($token)->post('/restapi/v1.0/account/~/extension/~/sms', [
      'from' => ['phoneNumber' => '+15551234567'],
      'to' => [],
      'text' => '',
    ]);

    if ($this->responseIndicatesMissingPermission($response, 'SMS')) {
      return [
        'success' => false,
        'message' => $this->smsPermissionHelpMessage(),
      ];
    }

    if ($response->successful() || in_array($response->status(), [400, 404, 422], true)) {
      return [
        'success' => true,
        'message' => 'SMS API permission verified.',
      ];
    }

    return [
      'success' => false,
      'message' => 'Could not verify SMS permission: '.$this->parseError($response),
    ];
  }

  /**
   * @return array{success: bool, message: string}
   */
  public function testFaxSendPermission(?string $token = null): array
  {
    $token = $token ?? $this->accessToken();

    if (! $token) {
      return [
        'success' => false,
        'message' => $this->lastError ?? 'RingCentral authentication failed.',
      ];
    }

    if ($this->hasScopePermission('Fax')) {
      return [
        'success' => true,
        'message' => 'Fax API permission verified on access token.',
      ];
    }

    $response = $this->authenticatedRequest($token)->post('/restapi/v1.0/account/~/extension/~/fax', [
      'to' => '',
      'coverPageText' => '',
    ]);

    if ($this->responseIndicatesMissingPermission($response, 'Fax')) {
      return [
        'success' => false,
        'message' => $this->faxPermissionHelpMessage(),
      ];
    }

    if ($response->successful() || in_array($response->status(), [400, 404, 422], true)) {
      return [
        'success' => true,
        'message' => 'Fax API permission verified.',
      ];
    }

    return [
      'success' => false,
      'message' => 'Could not verify Fax permission: '.$this->parseError($response),
    ];
  }

  public function smsPermissionHelpMessage(): string
  {
    return 'RingCentral app is missing SMS permission. In developers.ringcentral.com open your app → Permissions, enable SMS, save, then generate a new JWT and update Credential Vault.';
  }

  public function faxPermissionHelpMessage(): string
  {
    return 'RingCentral app is missing Fax permission. In developers.ringcentral.com open your app → Permissions, enable Fax, save, then generate a new JWT and update Credential Vault.';
  }

  public function sendSms(string $to, string $text, ?string $from = null): array
  {
    $token = $this->accessToken();

    if (! $token) {
      return ['success' => false, 'provider_message_id' => null, 'failure_reason' => $this->lastError ?? 'Authentication failed.'];
    }

    $fromNumber = $from ?: $this->resolveFromNumber($token);

    if (! $fromNumber) {
      return [
        'success' => false,
        'provider_message_id' => null,
        'failure_reason' => 'RingCentral sender phone number is not configured. Set the outbound SMS number in Credential Vault or assign an SMS-capable number to this extension.',
      ];
    }

    $response = $this->authenticatedRequest($token)->post('/restapi/v1.0/account/~/extension/~/sms', [
      'from' => ['phoneNumber' => $this->normalizePhone($fromNumber)],
      'to' => [['phoneNumber' => $this->normalizePhone($to)]],
      'text' => $text,
    ]);

    if (! $response->successful()) {
      return [
        'success' => false,
        'provider_message_id' => null,
        'failure_reason' => $this->parseError($response),
      ];
    }

    return [
      'success' => true,
      'provider_message_id' => (string) ($response->json('id') ?? Str::uuid()),
      'failure_reason' => null,
    ];
  }

  /**
   * Place a server-bridged (RingOut) call. RingCentral calls the caregiver's
   * phone (`$from`) first, then dials the client (`$to`) and connects the two
   * legs — so the client sees the agency caller ID, never the caregiver's cell.
   *
   * Requires the "RingOut" permission on the RingCentral app.
   *
   * @return array{success: bool, call_id: ?string, status: ?string, failure_reason: ?string}
   */
  public function ringOut(string $to, string $from, ?string $callerId = null): array
  {
    $token = $this->accessToken();

    if (! $token) {
      return ['success' => false, 'call_id' => null, 'status' => null, 'failure_reason' => $this->lastError ?? 'Authentication failed.'];
    }

    $payload = [
      'from' => ['phoneNumber' => $this->normalizePhone($from)],
      'to' => ['phoneNumber' => $this->normalizePhone($to)],
      'playPrompt' => false,
    ];

    $callerId = $callerId !== null ? trim($callerId) : null;

    if ($callerId) {
      $payload['callerId'] = ['phoneNumber' => $this->normalizePhone($callerId)];
    }

    $response = $this->authenticatedRequest($token)
      ->post('/restapi/v1.0/account/~/extension/~/ring-out', $payload);

    if (! $response->successful()) {
      return ['success' => false, 'call_id' => null, 'status' => null, 'failure_reason' => $this->parseError($response)];
    }

    return [
      'success' => true,
      'call_id' => (string) ($response->json('id') ?? Str::uuid()),
      'status' => (string) ($response->json('status.callStatus') ?? 'InProgress'),
      'failure_reason' => null,
    ];
  }

  /**
   * @return array{success: bool, provider_message_id: ?string, failure_reason: ?string}
   */
  public function sendFax(string $to, string $filePathOrContents, string $fileName, ?string $coverNote = null, bool $isContents = false): array
  {
    $token = $this->accessToken();

    if (! $token) {
      return ['success' => false, 'provider_message_id' => null, 'failure_reason' => $this->lastError ?? 'Authentication failed.'];
    }

    $contents = $isContents ? $filePathOrContents : file_get_contents($filePathOrContents);

    if ($contents === false || $contents === '') {
      return ['success' => false, 'provider_message_id' => null, 'failure_reason' => 'Fax attachment is not readable.'];
    }

    $response = $this->authenticatedRequest($token)
      ->attach('attachment', $contents, $fileName)
      ->post('/restapi/v1.0/account/~/extension/~/fax', [
        'to' => $this->normalizePhone($to),
        'coverPageText' => $coverNote ?: '',
      ]);

    if (! $response->successful()) {
      return [
        'success' => false,
        'provider_message_id' => null,
        'failure_reason' => $this->parseError($response),
      ];
    }

    return [
      'success' => true,
      'provider_message_id' => (string) ($response->json('id') ?? Str::uuid()),
      'failure_reason' => null,
    ];
  }

  /**
   * @return array<string, mixed>|null
   */
  public function getMessageStoreEntry(string $messageId): ?array
  {
    $token = $this->accessToken();

    if (! $token) {
      return null;
    }

    $response = $this->authenticatedRequest($token)
      ->get('/restapi/v1.0/account/~/extension/~/message-store/'.$messageId);

    if (! $response->successful()) {
      return null;
    }

    return $response->json();
  }

  /**
   * Inbound SMS / fax / voicemail entries from the extension message store —
   * lets the scheduled sync pick up texts even when webhooks are not set up.
   *
   * @return list<array<string, mixed>>
   */
  public function listRecentMessages(int $limit = 25, ?string $dateFrom = null): array
  {
    $token = $this->accessToken();

    if (! $token) {
      return [];
    }

    $response = $this->authenticatedRequest($token)->get('/restapi/v1.0/account/~/extension/~/message-store', [
      'direction' => 'Inbound',
      'perPage' => $limit,
      'dateFrom' => $dateFrom ?? now()->subDays(7)->toIso8601String(),
    ]);

    if (! $response->successful()) {
      return [];
    }

    return collect($response->json('records', []))
      ->filter(fn ($record) => is_array($record))
      ->values()
      ->all();
  }

  /**
   * @return list<array<string, mixed>>
   */
  public function listRecentCallLog(int $limit = 25): array
  {
    $token = $this->accessToken();

    if (! $token) {
      return [];
    }

    $response = $this->authenticatedRequest($token)->get('/restapi/v1.0/account/~/call-log', [
      'view' => 'Detailed',
      'direction' => 'Inbound',
      'perPage' => $limit,
    ]);

    if (! $response->successful()) {
      return [];
    }

    return collect($response->json('records', []))
      ->filter(fn ($record) => is_array($record))
      ->values()
      ->all();
  }

  protected function accessToken(): ?string
  {
    $payload = $this->requestAccessToken();

    if ($payload === null) {
      return null;
    }

    $token = trim((string) ($payload['access_token'] ?? ''));

    return $token !== '' ? $token : null;
  }

  /**
   * @return array{access_token?: string, scope?: string, expires_in?: int}|null
   */
  protected function requestAccessToken(): ?array
  {
    $clientHash = md5((string) config('ringcentral.client_id'));
    $cacheKey = 'ringcentral.access_token.v2.'.$clientHash;
    $legacyCacheKey = 'ringcentral.access_token.'.$clientHash;

    $cached = Cache::get($cacheKey);

    if (is_array($cached)) {
      return $this->normalizeTokenPayload($cached);
    }

    if ($cached !== null) {
      Cache::forget($cacheKey);
    }

    if (is_string(Cache::get($legacyCacheKey))) {
      Cache::forget($legacyCacheKey);
    }

    $payload = Cache::remember($cacheKey, 3000, function () {
      $server = rtrim((string) config('ringcentral.server_url'), '/');
      $clientId = (string) config('ringcentral.client_id');
      $clientSecret = (string) config('ringcentral.client_secret');
      $jwt = (string) config('ringcentral.jwt');

      if ($jwt !== '') {
        $response = Http::asForm()
          ->withBasicAuth($clientId, $clientSecret)
          ->timeout((int) config('ringcentral.timeout', 30))
          ->post($server.'/restapi/oauth/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
          ]);
      } else {
        $response = Http::asForm()
          ->withBasicAuth($clientId, $clientSecret)
          ->timeout((int) config('ringcentral.timeout', 30))
          ->post($server.'/restapi/oauth/token', [
            'grant_type' => 'client_credentials',
          ]);
      }

      if (! $response->successful()) {
        $this->lastError = $this->parseError($response);

        return null;
      }

      return [
        'access_token' => (string) $response->json('access_token'),
        'scope' => (string) $response->json('scope', ''),
        'expires_in' => (int) $response->json('expires_in', 3600),
      ];
    });

    if ($payload === null) {
      Cache::forget($cacheKey);
    }

    return is_array($payload) ? $this->normalizeTokenPayload($payload) : null;
  }

  /**
   * @param  array<string, mixed>|string  $payload
   * @return array{access_token: string, scope: string, expires_in: int}|null
   */
  protected function normalizeTokenPayload(array|string $payload): ?array
  {
    if (is_string($payload)) {
      $token = trim($payload);

      return $token !== '' ? ['access_token' => $token, 'scope' => '', 'expires_in' => 3600] : null;
    }

    $token = trim((string) ($payload['access_token'] ?? ''));

    if ($token === '') {
      return null;
    }

    return [
      'access_token' => $token,
      'scope' => (string) ($payload['scope'] ?? ''),
      'expires_in' => (int) ($payload['expires_in'] ?? 3600),
    ];
  }

  protected function authenticatedRequest(string $token): PendingRequest
  {
    $server = rtrim((string) config('ringcentral.server_url'), '/');

    return Http::withToken($token)
      ->acceptJson()
      ->timeout((int) config('ringcentral.timeout', 30))
      ->baseUrl($server);
  }

  protected function normalizePhone(string $phone): string
  {
    $digits = preg_replace('/\D/', '', $phone) ?? '';

    if (strlen($digits) === 10) {
      return '+1'.$digits;
    }

    if (str_starts_with($phone, '+')) {
      return $phone;
    }

    return '+'.$digits;
  }

  protected function parseError(\Illuminate\Http\Client\Response $response): string
  {
    $message = $response->json('message') ?? $response->json('error_description') ?? $response->body();

    if (! is_string($message)) {
      $message = json_encode($message);
    }

    if (is_string($message) && str_contains($message, '[SMS] permission')) {
      $message = $this->smsPermissionHelpMessage();
    } elseif (is_string($message) && str_contains($message, '[Fax] permission')) {
      $message = $this->faxPermissionHelpMessage();
    }

    return Str::limit(is_string($message) ? $message : (string) $message, 500);
  }

  protected function responseIndicatesMissingPermission(\Illuminate\Http\Client\Response $response, string $permission): bool
  {
    if ($response->status() !== 403) {
      return false;
    }

    $message = (string) ($response->json('message') ?? $response->body());

    return str_contains($message, "[{$permission}] permission");
  }
}

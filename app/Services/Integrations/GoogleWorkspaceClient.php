<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleWorkspaceClient
{
  protected ?string $lastError = null;

  public function isConfigured(): bool
  {
    return filled(config('google_workspace.client_id'))
      && filled(config('google_workspace.client_secret'))
      && filled(config('google_workspace.delegated_user'));
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
        'message' => 'Google Workspace client ID, secret, and delegated user are required.',
      ];
    }

    $token = $this->accessToken();

    if (! $token) {
      return [
        'success' => false,
        'message' => $this->lastError ?? 'Google Workspace authentication failed.',
      ];
    }

    $user = rawurlencode((string) config('google_workspace.delegated_user'));
    $response = Http::withToken($token)
      ->acceptJson()
      ->timeout((int) config('google_workspace.timeout', 30))
      ->get("https://gmail.googleapis.com/gmail/v1/users/{$user}/profile");

    if (! $response->successful()) {
      return [
        'success' => false,
        'message' => 'Gmail API profile check failed: '.$response->body(),
      ];
    }

    return [
      'success' => true,
      'message' => 'Google Workspace connected — '.$response->json('emailAddress', config('google_workspace.delegated_user')).' verified.',
    ];
  }

  /**
   * @return array{success: bool, provider_message_id: ?string, failure_reason: ?string}
   */
  public function sendEmail(string $to, string $subject, string $body, ?string $from = null): array
  {
    $token = $this->accessToken();

    if (! $token) {
      return ['success' => false, 'provider_message_id' => null, 'failure_reason' => $this->lastError ?? 'Authentication failed.'];
    }

    $fromAddress = $from ?: (string) config('google_workspace.delegated_user');
    $raw = $this->buildMime($fromAddress, $to, $subject, $body);
    $encoded = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

    $user = rawurlencode((string) config('google_workspace.delegated_user'));
    $response = Http::withToken($token)
      ->acceptJson()
      ->timeout((int) config('google_workspace.timeout', 30))
      ->post("https://gmail.googleapis.com/gmail/v1/users/{$user}/messages/send", [
        'raw' => $encoded,
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
   * @return list<array{id: string, from: string, subject: string, body: string}>
   */
  public function listInboundMessages(int $limit = 25): array
  {
    $token = $this->accessToken();

    if (! $token) {
      return [];
    }

    $user = rawurlencode((string) config('google_workspace.delegated_user'));
    $listResponse = Http::withToken($token)
      ->acceptJson()
      ->timeout((int) config('google_workspace.timeout', 30))
      ->get("https://gmail.googleapis.com/gmail/v1/users/{$user}/messages", [
        'q' => 'in:inbox newer_than:7d',
        'maxResults' => $limit,
      ]);

    if (! $listResponse->successful()) {
      return [];
    }

    $messages = [];

    foreach ($listResponse->json('messages', []) as $item) {
      $id = (string) ($item['id'] ?? '');
      if ($id === '') {
        continue;
      }

      $detail = Http::withToken($token)
        ->acceptJson()
        ->timeout((int) config('google_workspace.timeout', 30))
        ->get("https://gmail.googleapis.com/gmail/v1/users/{$user}/messages/{$id}", [
          'format' => 'full',
        ]);

      if (! $detail->successful()) {
        continue;
      }

      $headers = collect($detail->json('payload.headers', []));
      $fromHeader = $headers->first(fn (array $header) => ($header['name'] ?? '') === 'From');
      $subjectHeader = $headers->first(fn (array $header) => ($header['name'] ?? '') === 'Subject');
      $from = (string) ($fromHeader['value'] ?? '');
      $subject = (string) ($subjectHeader['value'] ?? '');
      $body = $this->extractGmailBody($detail->json('payload', []));

      $messages[] = [
        'id' => $id,
        'from' => $from,
        'subject' => $subject,
        'body' => $body,
      ];
    }

    return $messages;
  }

  /**
   * @param  array<string, mixed>  $payload
   */
  protected function extractGmailBody(array $payload): string
  {
    if (($payload['mimeType'] ?? '') === 'text/plain' && isset($payload['body']['data'])) {
      return $this->decodeGmailData((string) $payload['body']['data']);
    }

    foreach ($payload['parts'] ?? [] as $part) {
      if (is_array($part) && ($part['mimeType'] ?? '') === 'text/plain' && isset($part['body']['data'])) {
        return $this->decodeGmailData((string) $part['body']['data']);
      }
    }

    return '';
  }

  protected function decodeGmailData(string $data): string
  {
    $decoded = base64_decode(strtr($data, '-_', '+/'), true);

    return is_string($decoded) ? trim($decoded) : '';
  }

  protected function accessToken(): ?string
  {
    $cacheKey = 'google_workspace.access_token.'.md5((string) config('google_workspace.client_id'));

    return Cache::remember($cacheKey, 3000, function () {
      $refreshToken = (string) config('google_workspace.refresh_token');

      if ($refreshToken === '') {
        $this->lastError = 'Google Workspace refresh token is not configured.';

        return null;
      }

      $response = Http::asForm()
        ->timeout((int) config('google_workspace.timeout', 30))
        ->post('https://oauth2.googleapis.com/token', [
          'client_id' => config('google_workspace.client_id'),
          'client_secret' => config('google_workspace.client_secret'),
          'refresh_token' => $refreshToken,
          'grant_type' => 'refresh_token',
        ]);

      if (! $response->successful()) {
        $this->lastError = $this->parseError($response);

        return null;
      }

      return $response->json('access_token');
    });
  }

  protected function buildMime(string $from, string $to, string $subject, string $body): string
  {
    $headers = [
      'From: '.$from,
      'To: '.$to,
      'Subject: '.$subject,
      'MIME-Version: 1.0',
      'Content-Type: text/plain; charset=utf-8',
    ];

    return implode("\r\n", $headers)."\r\n\r\n".$body;
  }

  protected function parseError(\Illuminate\Http\Client\Response $response): string
  {
    $message = $response->json('error_description') ?? $response->json('error.message') ?? $response->body();

    return Str::limit(is_string($message) ? $message : json_encode($message), 500);
  }
}

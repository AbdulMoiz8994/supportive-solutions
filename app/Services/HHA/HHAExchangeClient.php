<?php

namespace App\Services\HHA;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HHAExchangeClient
{
    public function isConnected(): bool
    {
        return $this->getConnectionStatus()['connected'] === true;
    }

    /**
     * @return array{
     *     connected: bool,
     *     oauth_ok: bool,
     *     status: string,
     *     message: string,
     *     checks?: list<array{name: string, passed: bool, detail: string, duration_ms?: ?int}>
     * }
     */
    public function getConnectionStatus(): array
    {
        $checks = [];
        $clientId = (string) config('hha.client_id');
        $clientSecret = (string) config('hha.client_secret');
        $scope = (string) (config('hha.scope') ?: 'write:aggregator');
        $attestation = (string) config('hha.attestation_status', 'pending');
        $tokenUrl = $this->tokenUrl();
        $attestationApproved = $attestation === 'approved';

        $checks[] = [
            'name' => 'API credentials',
            'passed' => $clientId !== '' && $clientSecret !== '',
            'detail' => $clientId !== '' && $clientSecret !== ''
                ? 'Client ID and secret are configured'
                : 'Client ID and secret are required in Credential Vault',
        ];

        $checks[] = [
            'name' => 'OAuth scope',
            'passed' => $scope !== '',
            'detail' => $scope !== ''
                ? 'Scope: '.$scope
                : 'OAuth scope is required (write:aggregator)',
        ];

        $checks[] = [
            'name' => 'Token URL',
            'passed' => $tokenUrl !== '',
            'detail' => $tokenUrl !== ''
                ? $tokenUrl
                : 'Token URL is required (…/identity/connect/token)',
        ];

        $checks[] = [
            'name' => 'EVV attestation',
            'passed' => $attestationApproved,
            'detail' => $attestationApproved
                ? 'Attestation approved'
                : 'Attestation is "'.$attestation.'" — required for live EVV sync, not for OAuth Test 1-001',
        ];

        if ($clientId === '' || $clientSecret === '') {
            return [
                'connected' => false,
                'oauth_ok' => false,
                'status' => 'missing_credentials',
                'message' => 'HHAeXchange API credentials are not configured.',
                'checks' => $checks,
            ];
        }

        if ($tokenUrl === '') {
            return [
                'connected' => false,
                'oauth_ok' => false,
                'status' => 'not_configured',
                'message' => 'HHAeXchange token URL is not configured.',
                'checks' => $checks,
            ];
        }

        // Match Swagger Authorize: attempt OAuth even when attestation is still pending.
        $started = microtime(true);

        try {
            $this->forgetCachedToken();
            $token = $this->requestAccessToken();
            $durationMs = (int) round((microtime(true) - $started) * 1000);

            $checks[] = [
                'name' => 'OAuth token (Test 1-001)',
                'passed' => $token !== '',
                'detail' => 'Access token acquired from '.$tokenUrl.' ('.$durationMs.'ms)',
                'duration_ms' => $durationMs,
            ];

            if (! $attestationApproved) {
                return [
                    'connected' => false,
                    'oauth_ok' => true,
                    'status' => 'pending_attestation',
                    'message' => 'OAuth succeeded (same as Swagger Authorize). Set attestation to Approved before live EVV sync.',
                    'checks' => $checks,
                ];
            }

            return [
                'connected' => true,
                'oauth_ok' => true,
                'status' => 'connected',
                'message' => 'HHAeXchange OAuth connected — access token acquired (scope '.$scope.').',
                'checks' => $checks,
            ];
        } catch (\Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $checks[] = [
                'name' => 'OAuth token (Test 1-001)',
                'passed' => false,
                'detail' => $exception->getMessage(),
                'duration_ms' => $durationMs,
            ];

            return [
                'connected' => false,
                'oauth_ok' => false,
                'status' => 'error',
                'message' => 'HHAeXchange OAuth failed: '.$exception->getMessage(),
                'checks' => $checks,
            ];
        }
    }

    /**
     * @return array{success: bool, status: string, external_id: ?string, transaction_id: ?string, message: ?string, raw: ?array, http_status: ?int}
     */
    public function exportVisit(array $visitPayload): array
    {
        if (! $this->isConnected()) {
            return $this->notConnectedResult();
        }

        $body = array_key_exists('visits', $visitPayload)
            ? $visitPayload
            : ['visits' => [$visitPayload]];

        $response = $this->send(fn (PendingRequest $http) => $http->post($this->visitsPath(), $body));

        return $this->parseMutationResponse($response, 'visit', [200, 202]);
    }

    /**
     * @return array{success: bool, status: string, external_id: ?string, transaction_id: ?string, message: ?string, raw: ?array, http_status: ?int}
     */
    public function syncCaregiver(array $caregiverPayload): array
    {
        if (! $this->isConnected()) {
            return $this->notConnectedResult();
        }

        $response = $this->send(fn (PendingRequest $http) => $http->post($this->caregiversPath(), $caregiverPayload));

        return $this->parseMutationResponse($response, 'caregiver', [200, 201]);
    }

    /**
     * @return array{success: bool, status: string, external_id: ?string, transaction_id: ?string, message: ?string, raw: ?array, http_status: ?int}
     */
    public function updateVisit(string $evvmsid, array $visitPayload): array
    {
        if (! $this->isConnected()) {
            return $this->notConnectedResult();
        }

        $response = $this->send(
            fn (PendingRequest $http) => $http->put($this->visitsPath().'/'.ltrim($evvmsid, '/'), $visitPayload)
        );

        return $this->parseMutationResponse($response, 'visit', [200, 202]);
    }

    /**
     * @return array{success: bool, status: string, external_id: ?string, transaction_id: ?string, message: ?string, raw: ?array, http_status: ?int}
     */
    public function deleteVisit(string $evvmsid): array
    {
        if (! $this->isConnected()) {
            return $this->notConnectedResult();
        }

        $response = $this->send(
            fn (PendingRequest $http) => $http->delete($this->visitsPath().'/'.ltrim($evvmsid, '/'))
        );

        return $this->parseMutationResponse($response, 'visit', [200, 202]);
    }

    /**
     * @return array{success: bool, status: string, transaction_id: ?string, evvmsid: ?string, message: ?string, raw: ?array, http_status: ?int}
     */
    public function getTransaction(string $transactionId): array
    {
        if (! $this->isConnected()) {
            return array_merge($this->notConnectedResult(), ['evvmsid' => null]);
        }

        $response = $this->send(
            fn (PendingRequest $http) => $http->get($this->transactionsPath().'/'.ltrim($transactionId, '/'))
        );
        $body = $response->json() ?? [];
        $httpStatus = $response->status();

        if (! $response->successful()) {
            $message = is_array($body)
                ? (string) ($body['message'] ?? $body['error'] ?? $response->body())
                : $response->body();

            return [
                'success' => false,
                'status' => 'error',
                'transaction_id' => $transactionId,
                'evvmsid' => null,
                'message' => $message,
                'raw' => is_array($body) ? $body : null,
                'http_status' => $httpStatus,
            ];
        }

        $evvmsid = $body['evvmsid']
            ?? $body['evvMsId']
            ?? $body['EVVMSID']
            ?? data_get($body, 'visits.0.evvmsid')
            ?? data_get($body, 'data.evvmsid')
            ?? null;

        return [
            'success' => true,
            'status' => 'ok',
            'transaction_id' => $transactionId,
            'evvmsid' => is_scalar($evvmsid) ? (string) $evvmsid : null,
            'message' => null,
            'raw' => is_array($body) ? $body : null,
            'http_status' => $httpStatus,
        ];
    }

    /**
     * Raw POST used by Phase 1 validation scenarios (expects 400).
     *
     * @return array{success: bool, status: string, external_id: ?string, transaction_id: ?string, message: ?string, raw: ?array, http_status: ?int}
     */
    public function postCaregiverRaw(array $payload): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post($this->caregiversPath(), $payload));

        return $this->parseMutationResponse($response, 'caregiver', [200, 201]);
    }

    public function apiBaseUrl(): string
    {
        return $this->normalizeApiBaseUrl((string) config('hha.api_url'));
    }

    public function tokenUrl(): string
    {
        $configured = rtrim((string) config('hha.token_url'), '/');

        if ($configured !== '') {
            return $configured;
        }

        $apiUrl = $this->apiBaseUrl();

        return $apiUrl !== '' ? $apiUrl.'/identity/connect/token' : '';
    }

    /**
     * Host only — strip accidental /api/v1|/api/v2 paths users paste from docs.
     */
    public function normalizeApiBaseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');

        if ($url === '') {
            return '';
        }

        // Users often paste …/api/v2/ from the endpoint sheet; paths are appended separately.
        $url = preg_replace('#/api/v\d+/?$#i', '', $url) ?? $url;

        return rtrim($url, '/');
    }

    public function visitsPath(): string
    {
        return (string) config('hha.endpoints.visits', '/api/v2/visits');
    }

    public function caregiversPath(): string
    {
        return (string) config('hha.endpoints.caregivers', '/api/v2/caregivers');
    }

    public function transactionsPath(): string
    {
        return (string) config('hha.endpoints.transactions', '/api/v2/visits/transactions');
    }

    public function accessToken(): string
    {
        $cacheKey = $this->tokenCacheKey();
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->requestAccessToken();
    }

    public function forgetCachedToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    protected function requestAccessToken(): string
    {
        $tokenUrl = $this->tokenUrl();
        $clientId = (string) config('hha.client_id');
        $clientSecret = (string) config('hha.client_secret');
        $scope = (string) (config('hha.scope') ?: 'write:aggregator');
        $origin = $this->apiBaseUrl() ?: 'https://implementation.hhaexchange.com';

        if ($tokenUrl === '') {
            throw new RuntimeException('HHAeXchange token URL is not configured.');
        }

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('HHAeXchange client_id and client_secret are required.');
        }

        $form = [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => $scope,
        ];

        // Mirror Swagger Authorize headers — some HHAX edge/WAF setups block bare PHP clients.
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Origin' => $origin,
            'Referer' => rtrim($origin, '/').'/api/index.html',
        ];

        $response = Http::asForm()
            ->timeout((int) config('hha.timeout', 30))
            ->withHeaders($headers)
            ->post($tokenUrl, $form);

        // IdentityServer may prefer HTTP Basic for the client secret.
        if (in_array($response->status(), [401, 403], true)) {
            $basic = Http::asForm()
                ->timeout((int) config('hha.timeout', 30))
                ->withBasicAuth($clientId, $clientSecret)
                ->withHeaders($headers)
                ->post($tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'scope' => $scope,
                ]);

            if ($basic->successful() || $basic->status() !== $response->status()) {
                $response = $basic;
            }
        }

        if (! $response->successful()) {
            $body = $response->json() ?? [];
            $message = $body['error_description'] ?? $body['error'] ?? $response->body();
            $snippet = is_string($message) ? trim(preg_replace('/\s+/', ' ', $message) ?? $message) : json_encode($message);
            if (strlen($snippet) > 300) {
                $snippet = substr($snippet, 0, 300).'…';
            }

            if ($response->status() === 403 && str_contains(strtolower($snippet), 'forbidden')) {
                $snippet .= ' — AWS ELB blocked this server IP (not an invalid client_id). Swagger in a browser can still work on another network. Ask HHAX EDI to allowlist your public IP for Implementation API access, or run the app from an allowlisted network.';
            }

            throw new RuntimeException(
                'HHAeXchange OAuth token request failed (HTTP '.$response->status().') at '.$tokenUrl.': '.$snippet
            );
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('HHAeXchange OAuth token response did not include access_token.');
        }

        $expiresIn = (int) ($response->json('expires_in') ?? config('hha.token_cache_seconds', 1500));
        $ttl = max(60, min($expiresIn - 60, 1740));
        Cache::put($this->tokenCacheKey(), $token, now()->addSeconds($ttl));

        return $token;
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->apiBaseUrl())
            ->withToken($this->accessToken())
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout((int) config('hha.timeout', 30));
    }

    /**
     * @param  callable(PendingRequest): Response  $callback
     */
    protected function send(callable $callback): Response
    {
        $response = $callback($this->request());

        if ($response->status() === 401) {
            $this->forgetCachedToken();
            $response = $callback($this->request());
        }

        return $response;
    }

    /**
     * @param  list<int>  $successStatuses
     * @return array{success: bool, status: string, external_id: ?string, transaction_id: ?string, message: ?string, raw: ?array, http_status: ?int}
     */
    protected function parseMutationResponse(Response $response, string $entity, array $successStatuses): array
    {
        $body = $response->json() ?? [];
        $httpStatus = $response->status();
        $transactionId = $body['transactionId']
            ?? $body['transaction_id']
            ?? $body['TransactionId']
            ?? data_get($body, 'data.transactionId')
            ?? null;

        if (in_array($httpStatus, $successStatuses, true)) {
            $externalId = $body['id']
                ?? $body[$entity.'Id']
                ?? $body['externalId']
                ?? $body['visitId']
                ?? $body['caregiverId']
                ?? $body['evvmsid']
                ?? $transactionId
                ?? null;

            Log::info('HHAeXchange '.$entity.' sync succeeded', [
                'status' => $httpStatus,
                'transaction_id' => $transactionId,
                'external_id' => $externalId,
            ]);

            return [
                'success' => true,
                'status' => 'synced',
                'external_id' => is_scalar($externalId) ? (string) $externalId : null,
                'transaction_id' => is_scalar($transactionId) ? (string) $transactionId : null,
                'message' => null,
                'raw' => is_array($body) ? $body : null,
                'http_status' => $httpStatus,
            ];
        }

        $message = is_array($body)
            ? (string) ($body['message'] ?? $body['error'] ?? $body['title'] ?? $response->body())
            : $response->body();

        Log::warning('HHAeXchange '.$entity.' sync failed', [
            'status' => $httpStatus,
            'message' => $message,
            'transaction_id' => $transactionId,
        ]);

        return [
            'success' => false,
            'status' => 'error',
            'external_id' => null,
            'transaction_id' => is_scalar($transactionId) ? (string) $transactionId : null,
            'message' => $message,
            'raw' => is_array($body) ? $body : null,
            'http_status' => $httpStatus,
        ];
    }

    /**
     * @return array{success: bool, status: string, external_id: ?string, transaction_id: ?string, message: ?string, raw: ?array, http_status: ?int}
     */
    protected function notConnectedResult(): array
    {
        return [
            'success' => false,
            'status' => $this->getConnectionStatus()['status'],
            'external_id' => null,
            'transaction_id' => null,
            'message' => null,
            'raw' => null,
            'http_status' => null,
        ];
    }

    protected function tokenCacheKey(): string
    {
        return 'hha.oauth.'.md5((string) config('hha.client_id').'|'.(string) config('hha.scope'));
    }
}

<?php

namespace App\Services\Availity;

use App\Models\PayrollClaim;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AvailityClient
{
    public function __construct(
        protected AvailityClaimPayloadMapper $payloadMapper
    ) {}

    public function isProduction(): bool
    {
        return config('services.availity.env') === 'production';
    }

    public function apiBaseUrl(): string
    {
        return rtrim((string) config('services.availity.api_base_url', 'https://api.availity.com/availity/v1'), '/');
    }

    public function tokenUrl(): string
    {
        return (string) config('services.availity.token_url', 'https://api.availity.com/v1/token');
    }

    /**
     * @deprecated Use apiBaseUrl() — kept for backward-compatible tests.
     */
    public function baseUrl(): string
    {
        return $this->apiBaseUrl();
    }

    /**
     * OAuth client_id (Availity API Key).
     */
    public function clientId(): string
    {
        $key = $this->isProduction()
            ? config('services.availity.prod_key')
            : config('services.availity.demo_key');

        if (! $key) {
            throw new RuntimeException('Availity client ID is not configured for the '.$this->environmentLabel().' environment.');
        }

        return (string) $key;
    }

    /**
     * @deprecated Use clientId() — kept for existing callers/tests.
     */
    public function apiKey(): string
    {
        return $this->clientId();
    }

    public function clientSecret(): string
    {
        $secret = $this->isProduction()
            ? config('services.availity.prod_secret')
            : config('services.availity.demo_secret');

        if (! $secret) {
            throw new RuntimeException('Availity client secret is not configured for the '.$this->environmentLabel().' environment.');
        }

        return (string) $secret;
    }

    public function scope(): string
    {
        return $this->isProduction()
            ? (string) config('services.availity.scope_prod', 'healthcare-hipaa-transactions')
            : (string) config('services.availity.scope_demo', 'healthcare-hipaa-transactions-demo');
    }

    public function environmentLabel(): string
    {
        return $this->isProduction() ? 'production' : 'demo';
    }

    public function professionalClaimsPath(): string
    {
        return (string) config('services.availity.endpoints.professional_claims', '/professional-claims');
    }

    /**
     * @param  array<string, mixed>  $internalPayload
     * @return array{success: bool, claim_id: ?string, status: string, raw: array}
     */
    public function submitClaim(array $internalPayload): array
    {
        $availityPayload = $this->payloadMapper->toProfessionalClaim($internalPayload);

        $this->log('info', 'Submitting professional claim to Availity', [
            'environment' => $this->environmentLabel(),
            'endpoint'    => $this->professionalClaimsUrl(),
            'reference'   => $internalPayload['referenceNumber'] ?? null,
        ]);

        $response = $this->request()->post($this->professionalClaimsPath(), $availityPayload);

        return $this->parseSubmissionResponse($response, $internalPayload, $availityPayload);
    }

    /**
     * @return array{success: bool, claim_id: ?string, status: string, raw: array}
     */
    public function checkClaimStatus(string $claimId): array
    {
        $path = rtrim($this->professionalClaimsPath(), '/').'/'.urlencode($claimId);

        $this->log('info', 'Polling Availity professional claim status', [
            'environment' => $this->environmentLabel(),
            'claim_id'    => $claimId,
        ]);

        $response = $this->request()->get($path);

        return $this->parseStatusResponse($response, $claimId);
    }

    public function claimStatusesPath(): string
    {
        return (string) config('services.availity.endpoints.claim_statuses', '/claim-statuses');
    }

    /**
     * HIPAA 276 claim status inquiry (form-encoded POST with X-HTTP-Method-Override: GET).
     *
     * @param  array<string, string>  $formFields
     * @return array{success: bool, status: string, reference_id: ?string, raw: array, message: ?string}
     */
    public function inquireClaimStatus(array $formFields): array
    {
        $this->log('info', 'Inquiring Availity claim status (276)', [
            'environment' => $this->environmentLabel(),
            'claimNumber' => $formFields['claimNumber'] ?? null,
        ]);

        $response = $this->statusInquiryRequest()
            ->post($this->claimStatusesPath(), $formFields);

        return $this->parseClaimStatusInquiryResponse($response);
    }

    public function accessToken(): string
    {
        $cacheKey = 'availity.oauth.'.$this->environmentLabel().'.'.md5($this->clientId());
        $ttlSeconds = (int) config('services.availity.token_cache_seconds', 240);

        return Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function (): string {
            $response = Http::asForm()
                ->timeout((int) config('services.availity.timeout', 30))
                ->post($this->tokenUrl(), [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                    'scope'         => $this->scope(),
                ]);

            if (! $response->successful()) {
                $body = $response->json() ?? [];
                $message = $body['error_description'] ?? $body['error'] ?? $response->body();

                $this->log('error', 'Availity OAuth token request failed', [
                    'environment' => $this->environmentLabel(),
                    'status_code' => $response->status(),
                    'response'    => is_array($body) ? $body : ['message' => (string) $message],
                ]);

                throw new RuntimeException('Availity OAuth token request failed: '.(string) $message);
            }

            $token = $response->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Availity OAuth token response did not include access_token.');
            }

            $this->log('info', 'Availity OAuth token obtained', [
                'environment' => $this->environmentLabel(),
                'expires_in'  => $response->json('expires_in'),
            ]);

            return $token;
        });
    }

    public function professionalClaimsUrl(): string
    {
        return $this->apiBaseUrl().$this->professionalClaimsPath();
    }

    protected function request(): PendingRequest
    {
        $request = Http::baseUrl($this->apiBaseUrl())
            ->withToken($this->accessToken())
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout((int) config('services.availity.timeout', 30));

        if ($scenarioId = config('services.availity.mock_scenario_id')) {
            $request = $request->withHeaders(['X-Api-Mock-Scenario-ID' => $scenarioId]);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $internalPayload
     * @param  array<string, mixed>  $availityPayload
     * @return array{success: bool, claim_id: ?string, status: string, raw: array}
     */
    protected function parseSubmissionResponse(Response $response, array $internalPayload, array $availityPayload): array
    {
        $body = $response->json() ?? [];
        $claimId = $this->resolveClaimId($response, $body);

        if ($response->status() === 202 || ($response->successful() && $claimId)) {
            $result = [
                'success'  => true,
                'claim_id' => $claimId,
                'status'   => $response->status() === 202 ? PayrollClaim::STATUS_PENDING : $this->normalizeStatus($body['status'] ?? 'submitted'),
                'raw'      => array_merge(is_array($body) ? $body : [], [
                    'httpStatus'      => $response->status(),
                    'location'        => $response->header('Location'),
                    'availityPayload' => $availityPayload,
                ]),
            ];

            $this->log('info', 'Availity professional claim accepted', $result);

            return $result;
        }

        $error = $body['message'] ?? $body['userMessage'] ?? $body['error'] ?? $response->body();

        $result = [
            'success'  => false,
            'claim_id' => null,
            'status'   => PayrollClaim::STATUS_FAILED,
            'raw'      => is_array($body) ? $body : ['message' => (string) $error],
        ];

        $this->log('error', 'Availity professional claim submission failed', [
            'status_code' => $response->status(),
            'reference'   => $internalPayload['referenceNumber'] ?? null,
            'response'    => $result['raw'],
        ]);

        return $result;
    }

    /**
     * @return array{success: bool, claim_id: ?string, status: string, raw: array}
     */
    protected function parseStatusResponse(Response $response, string $claimId): array
    {
        $body = $response->json() ?? [];

        if ($response->status() === 202) {
            return [
                'success'  => true,
                'claim_id' => $claimId,
                'status'   => PayrollClaim::STATUS_PENDING,
                'raw'      => array_merge(is_array($body) ? $body : [], [
                    'httpStatus' => 202,
                    'message'    => $response->header('X-Status-Message'),
                ]),
            ];
        }

        if ($response->successful()) {
            $result = [
                'success'  => true,
                'claim_id' => $body['id'] ?? $body['claimId'] ?? $body['claim_id'] ?? $claimId,
                'status'   => $this->normalizeStatus($body['status'] ?? $body['claimStatus'] ?? 'submitted'),
                'raw'      => is_array($body) ? $body : [],
            ];

            $this->log('info', 'Availity professional claim status retrieved', $result);

            return $result;
        }

        $error = $body['message'] ?? $body['error'] ?? $response->body();

        $result = [
            'success'  => false,
            'claim_id' => $claimId,
            'status'   => PayrollClaim::STATUS_FAILED,
            'raw'      => is_array($body) ? $body : ['message' => (string) $error],
        ];

        $this->log('error', 'Availity professional claim status check failed', [
            'status_code' => $response->status(),
            'claim_id'    => $claimId,
            'response'    => $result['raw'],
        ]);

        return $result;
    }

    protected function statusInquiryRequest(): PendingRequest
    {
        $request = Http::baseUrl($this->apiBaseUrl())
            ->withToken($this->accessToken())
            ->withHeaders([
                'Accept' => 'application/json',
                'X-HTTP-Method-Override' => 'GET',
            ])
            ->asForm()
            ->timeout((int) config('services.availity.timeout', 30));

        if ($scenarioId = config('services.availity.mock_scenario_id')) {
            $request = $request->withHeaders(['X-Api-Mock-Scenario-ID' => $scenarioId]);
        }

        return $request;
    }

    /**
     * @return array{success: bool, status: string, reference_id: ?string, raw: array, message: ?string}
     */
    protected function parseClaimStatusInquiryResponse(Response $response): array
    {
        $body = $response->json() ?? [];

        if ($response->status() === 202) {
            return [
                'success' => true,
                'status' => 'pending',
                'reference_id' => $this->resolveClaimId($response, $body),
                'raw' => array_merge(is_array($body) ? $body : [], [
                    'httpStatus' => 202,
                    'location' => $response->header('Location'),
                ]),
                'message' => $response->header('X-Status-Message'),
            ];
        }

        if ($response->successful()) {
            $statuses = $body['claimStatuses'] ?? [];
            $first = is_array($statuses[0] ?? null) ? $statuses[0] : [];
            $status = $this->extractClaimStatusLabel($first, $body);

            $result = [
                'success' => true,
                'status' => $status,
                'reference_id' => $first['id'] ?? $this->resolveClaimId($response, $body),
                'raw' => is_array($body) ? $body : [],
                'message' => null,
            ];

            $this->log('info', 'Availity claim status inquiry returned results', [
                'status' => $status,
                'totalCount' => $body['totalCount'] ?? count($statuses),
            ]);

            return $result;
        }

        $message = $body['userMessage'] ?? $body['developerMessage'] ?? $body['message'] ?? $body['error'] ?? $response->body();

        $this->log('error', 'Availity claim status inquiry failed', [
            'status_code' => $response->status(),
            'response' => is_array($body) ? $body : ['message' => (string) $message],
        ]);

        return [
            'success' => false,
            'status' => 'failed',
            'reference_id' => null,
            'raw' => is_array($body) ? $body : ['message' => (string) $message],
            'message' => (string) $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $body
     */
    protected function extractClaimStatusLabel(array $record, array $body): string
    {
        foreach ([
            $record['status'] ?? null,
            $record['statusCode'] ?? null,
            $record['categoryCode'] ?? null,
            $record['claimStatus'] ?? null,
            $body['status'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return strtolower($candidate);
            }
        }

        return ($body['totalCount'] ?? 0) > 0 ? 'submitted' : 'pending';
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function resolveClaimId(Response $response, array $body): ?string
    {
        if ($id = $body['id'] ?? $body['claimId'] ?? $body['claim_id'] ?? null) {
            return (string) $id;
        }

        $location = $response->header('Location');

        if (! $location) {
            return null;
        }

        $path = parse_url($location, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', (string) $path)));

        return $segments !== [] ? (string) end($segments) : null;
    }

    protected function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'accepted', 'approved', 'paid' => PayrollClaim::STATUS_APPROVED,
            'rejected', 'denied'          => PayrollClaim::STATUS_REJECTED,
            'pending', 'processing'       => PayrollClaim::STATUS_PENDING,
            'failed', 'error'             => PayrollClaim::STATUS_FAILED,
            default                       => PayrollClaim::STATUS_SUBMITTED,
        };
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        Log::channel('availity')->{$level}($message, $context);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        try {
            $this->accessToken();

            return [
                'success' => true,
                'message' => 'Availity OAuth connected for the '.$this->environmentLabel().' environment.',
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }
}

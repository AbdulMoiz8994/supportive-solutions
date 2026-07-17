<?php

namespace App\Services\Availity;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Live Medicaid / MCO eligibility via Availity Coverage API (270/271-style).
 * Falls back gracefully when credentials are missing.
 */
class AvailityEligibilityService
{
    public function __construct(protected AvailityClient $availity) {}

    public function isConfigured(): bool
    {
        try {
            $this->availity->clientId();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{live: bool, active: ?bool, payer_name: ?string, plan_name: ?string, message: string, raw: ?array}
     */
    public function inquire(string $memberId, ?Carbon $dob = null, ?string $payerId = null): array
    {
        if (! $this->isConfigured()) {
            return [
                'live' => false,
                'active' => null,
                'payer_name' => null,
                'plan_name' => null,
                'message' => 'Availity credentials not configured — using offline checks only.',
                'raw' => null,
            ];
        }

        $payerId = $payerId ?: (string) config('services.availity.default_payer_id', 'BCBSF');

        $query = array_filter([
            'payerId' => $payerId,
            'memberId' => $memberId,
            'patientBirthDate' => $dob?->format('Y-m-d'),
        ]);

        try {
            $path = (string) config('services.availity.endpoints.coverages', '/coverages');
            $response = Http::baseUrl($this->availity->apiBaseUrl())
                ->withToken($this->availity->accessToken())
                ->acceptJson()
                ->timeout((int) config('services.availity.timeout', 30))
                ->get($path, $query);

            $body = $response->json() ?? [];

            if (! $response->successful()) {
                $message = is_array($body)
                    ? (string) ($body['userMessage'] ?? $body['message'] ?? $response->body())
                    : $response->body();

                return [
                    'live' => true,
                    'active' => null,
                    'payer_name' => null,
                    'plan_name' => null,
                    'message' => 'Availity eligibility inquiry failed: '.$message,
                    'raw' => is_array($body) ? $body : null,
                ];
            }

            $coverage = $this->extractCoverage($body);
            $active = $coverage['active'] ?? null;

            return [
                'live' => true,
                'active' => $active,
                'payer_name' => $coverage['payer_name'] ?? null,
                'plan_name' => $coverage['plan_name'] ?? null,
                'message' => $active === true
                    ? 'Active coverage confirmed via Availity.'
                    : ($active === false
                        ? 'No active coverage returned from Availity.'
                        : 'Availity responded — review coverage details.'),
                'raw' => is_array($body) ? $body : null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Availity eligibility inquiry error', ['error' => $exception->getMessage()]);

            return [
                'live' => true,
                'active' => null,
                'payer_name' => null,
                'plan_name' => null,
                'message' => 'Availity eligibility unavailable: '.$exception->getMessage(),
                'raw' => null,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{active: ?bool, payer_name: ?string, plan_name: ?string}
     */
    protected function extractCoverage(array $body): array
    {
        $coverages = $body['coverages'] ?? $body['coverage'] ?? $body;

        if (isset($coverages[0]) && is_array($coverages[0])) {
            $first = $coverages[0];
        } elseif (is_array($coverages) && isset($coverages['status'])) {
            $first = $coverages;
        } else {
            return ['active' => null, 'payer_name' => null, 'plan_name' => null];
        }

        $status = strtolower((string) ($first['status'] ?? $first['coverageStatus'] ?? ''));
        $active = in_array($status, ['active', 'eligible', '1', 'true'], true)
            ? true
            : (in_array($status, ['inactive', 'ineligible', 'terminated', '0', 'false'], true) ? false : null);

        return [
            'active' => $active,
            'payer_name' => $first['payerName'] ?? $first['payer']['name'] ?? null,
            'plan_name' => $first['planName'] ?? $first['plan']['name'] ?? null,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\GlobalIntegrationHealth;
use App\Models\IntegrationCredential;
use App\Models\User;
use App\Services\Directory\IntegrationConnectionTestService;
use App\Support\IntegrationTestResult;
use Illuminate\Support\Collection;

class GlobalIntegrationTestService
{
    public function __construct(
        protected IntegrationConnectionTestService $connectionTest,
    ) {}

    /**
     * @return Collection<string, GlobalIntegrationHealth>
     */
    public function healthIndex(): Collection
    {
        return GlobalIntegrationHealth::query()->get()->keyBy('slug');
    }

    /**
     * @return array<string, mixed>
     */
    public function test(string $slug, ?User $actor = null, ?array $draft = null): array
    {
        $slug = strtolower(trim($slug));
        $result = $this->resolveTest($slug, $draft);
        $payload = $result->toArray();
        $health = $this->record($slug, $payload, $actor);

        return array_merge($payload, [
            'slug' => $slug,
            'tested_at' => $health->last_tested_at?->toIso8601String() ?? now()->toIso8601String(),
            'status' => $health->status,
            'status_label' => $health->statusLabel(),
            'badge_class' => $health->statusBadgeClass(),
            'display_status' => $health->displayStatus(),
        ]);
    }

    public function isTestable(string $slug): bool
    {
        $slug = strtolower(trim($slug));

        if ($this->catalogEntry($slug) !== null) {
            return true;
        }

        return array_key_exists($slug, IntegrationCredential::supportedKeys());
    }

    protected function resolveTest(string $slug, ?array $draft = null): IntegrationTestResult
    {
        $entry = $this->catalogEntry($slug);

        if ($entry !== null) {
            return match ($slug) {
                'state-portals' => $this->connectionTest->testStatePortals(),
                'sam-oig' => $this->connectionTest->testSamOig(),
                'retell' => $this->connectionTest->testRetell(),
                'uhc-edi' => $this->connectionTest->testUhcEdi(),
                'billing-claims' => $this->connectionTest->testBillingClaims(),
                default => $entry['credential_key']
                    ? $this->connectionTest->testCredentialKey($entry['credential_key'], $draft)
                    : IntegrationTestResult::make(
                        false,
                        GlobalIntegrationHealth::STATUS_NOT_CONFIGURED,
                        'No credential mapping exists for this integration.',
                    ),
            };
        }

        if (array_key_exists($slug, IntegrationCredential::supportedKeys())) {
            return $this->connectionTest->testCredentialKey($slug, $draft);
        }

        return IntegrationTestResult::make(
            false,
            GlobalIntegrationHealth::STATUS_NOT_CONFIGURED,
            'Unknown integration: '.$slug,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function record(string $slug, array $payload, ?User $actor): GlobalIntegrationHealth
    {
        $status = (string) $payload['status'];

        if (! in_array($status, [
            GlobalIntegrationHealth::STATUS_CONNECTED,
            GlobalIntegrationHealth::STATUS_ERROR,
            GlobalIntegrationHealth::STATUS_NOT_CONFIGURED,
            GlobalIntegrationHealth::STATUS_PARTIAL,
        ], true)) {
            $status = ($payload['success'] ?? false)
                ? GlobalIntegrationHealth::STATUS_CONNECTED
                : GlobalIntegrationHealth::STATUS_ERROR;
        }

        return GlobalIntegrationHealth::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'status' => $status,
                'message' => (string) ($payload['summary'] ?? $payload['message'] ?? ''),
                'latency_ms' => (int) ($payload['latency_ms'] ?? 0),
                'details' => [
                    'message' => $payload['message'] ?? null,
                    'summary' => $payload['summary'] ?? null,
                    'method' => $payload['method'] ?? null,
                    'recommendation' => $payload['recommendation'] ?? null,
                    'checks' => $payload['checks'] ?? [],
                ],
                'last_tested_at' => now(),
                'last_tested_by' => $actor?->id,
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function catalogEntry(string $slug): ?array
    {
        return collect(config('global_settings.integrations', []))
            ->first(fn (array $entry) => ($entry['slug'] ?? null) === $slug);
    }
}

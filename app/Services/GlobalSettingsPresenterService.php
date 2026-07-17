<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CaregiverActivationCode;
use App\Models\GlobalIntegrationHealth;
use App\Models\IntegrationCredential;
use App\Models\Organization;
use App\Models\PayrollAuditLog;
use App\Support\DirectoryIntegrationCatalog;
use Illuminate\Support\Str;

class GlobalSettingsPresenterService
{
    public function __construct(
        protected GlobalSettingsService $settings,
        protected CredentialVaultService $vault,
        protected AgencyIdentityService $agencyIdentity,
        protected GlobalIntegrationTestService $integrationTests,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forIndex(?Organization $organization, array $hhaConnection): array
    {
        $settings = $this->settings->all();
        $healthIndex = $this->integrationTests->healthIndex();

        return [
            'sectionSummaries' => $this->sectionSummaries($settings),
            'integrations' => $this->integrations($hhaConnection, $healthIndex),
            'billingChannels' => $this->billingChannels($settings, $healthIndex),
            'vaultRows' => $this->vaultRows($healthIndex, $organization?->id),
            'integrationStatuses' => $this->integrationStatuses($healthIndex),
            'programRules' => config('global_settings.program_rules', []),
            'auditPreview' => $this->auditPreview(),
            'activationCodes' => $this->activationCodes($organization),
            'lockedRules' => config('global_settings.locked_rules', []),
            'supportedLanguages' => config('global_settings.supported_languages', []),
            'catalog' => config('global_settings'),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    protected function sectionSummaries(array $settings): array
    {
        $mich = number_format((float) ($settings['programs.mich_hourly_rate'] ?? 30), 0);
        $dhs = number_format((float) ($settings['programs.dhs_hourly_rate'] ?? 27), 0);
        $wage = number_format((float) ($settings['programs.default_caregiver_wage'] ?? 15), 0);
        $grace = (int) ($settings['programs.pay_grace_days'] ?? 10);

        return [
            'programs-rates' => "MICH \${$mich} · DHS \${$dhs} · wage \${$wage} · ~{$grace}-day grace",
            'integrations' => 'AccountantsWorld · HHAeXchange · RingCentral · Availity · Google',
            'billing-claims' => '837P · DHS email · Sigma portal · ASW fallback',
            'credential-vault' => 'CHAMPS/MILogin · MCO portals · Sigma · ICHAT (RPA)',
            'security-compliance' => 'HIPAA · BAA · encryption · 7-yr retention · audit',
            'access-activation' => 'Invite-only caregiver-app codes',
            'ai-automation' => '2% miss-rate ceiling · default autonomy · approval thresholds',
            'notifications-language' => 'English · Arabic',
        ];
    }

    /**
     * @param  array<string, mixed>  $hhaConnection
     * @param  \Illuminate\Support\Collection<string, GlobalIntegrationHealth>  $healthIndex
     * @return list<array<string, mixed>>
     */
    protected function integrations(array $hhaConnection, $healthIndex): array
    {
        $vaultSummary = $this->vault->summaryForView();

        return collect(config('global_settings.integrations', []))
            ->map(function (array $entry) use ($vaultSummary, $hhaConnection, $healthIndex) {
                $slug = $entry['slug'] ?? null;
                $key = $entry['credential_key'] ?? null;
                $health = $slug ? $healthIndex->get($slug) : null;
                $status = $health?->displayStatus();

                if (! $status) {
                    $status = $entry['static_status'] ?? null;

                    if ($key === IntegrationCredential::KEY_HHA) {
                        $status = ($hhaConnection['connected'] ?? false)
                            ? 'Connected · live'
                            : ($hhaConnection['message'] ?? 'Not configured');
                    } elseif ($key && isset($vaultSummary[$key])) {
                        $configured = $vaultSummary[$key]['configured'] ?? false;
                        $status = $configured
                            ? 'Configured · not tested yet'
                            : 'Not configured';
                    } elseif (! $status) {
                        $status = 'Not configured';
                    }
                }

                return array_merge($entry, [
                    'slug' => $slug,
                    'status' => $status,
                    'health_status' => $health?->status,
                    'health_badge' => $health?->statusBadgeClass(),
                    'last_tested_at' => $health?->last_tested_at?->toIso8601String(),
                    'manage_url' => route('settings.global', [
                        'tab' => $entry['manage_tab'] ?? 'integrations',
                        'integration' => $key,
                    ]),
                    'testable' => $slug !== null,
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  \Illuminate\Support\Collection<string, GlobalIntegrationHealth>  $healthIndex
     * @return list<array<string, mixed>>
     */
    protected function billingChannels(array $settings, $healthIndex): array
    {
        $vaultSummary = $this->vault->summaryForView();

        $channels = [
            [
                'slug' => 'availity',
                'label' => 'MICH 837P · Availity',
                'purpose' => 'Professional claim submission for MCO members',
                'method' => 'api',
                'vault_key' => IntegrationCredential::KEY_AVAILITY,
                'manage_tab' => 'credential-vault',
            ],
            [
                'slug' => 'google-workspace',
                'label' => 'DHS Home Help · Google Workspace',
                'purpose' => 'Email Home Help invoices to ASW',
                'method' => 'api',
                'vault_key' => IntegrationCredential::KEY_GOOGLE_WORKSPACE,
                'manage_tab' => 'credential-vault',
            ],
            [
                'slug' => IntegrationCredential::KEY_SIGMA,
                'label' => 'Sigma Portal · RPA',
                'purpose' => 'Portal posting after ASW invoice delivery',
                'method' => 'rpa',
                'vault_key' => IntegrationCredential::KEY_SIGMA,
                'manage_tab' => 'credential-vault',
            ],
        ];

        return collect($channels)->map(function (array $channel) use ($vaultSummary, $healthIndex) {
            $slug = $channel['slug'];
            $key = $channel['vault_key'];
            $health = $healthIndex->get($slug);
            $configured = $vaultSummary[$key]['configured'] ?? false;

            return array_merge($channel, [
                'configured' => $configured,
                'status' => $health?->displayStatus() ?? ($configured ? 'Configured · not tested yet' : 'Not configured'),
                'health_badge' => $health?->statusBadgeClass(),
            ]);
        })->values()->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<string, GlobalIntegrationHealth>  $healthIndex
     * @return array<string, array{status: string, message: ?string, badge: string, tested_at: ?string}>
     */
    protected function integrationStatuses($healthIndex): array
    {
        return $healthIndex->mapWithKeys(fn (GlobalIntegrationHealth $health, string $slug) => [
            $slug => [
                'status' => $health->status,
                'message' => $health->detailMessage(),
                'summary' => $health->message,
                'badge' => $health->statusBadgeClass(),
                'tested_at' => $health->last_tested_at?->toIso8601String(),
                'label' => $health->statusLabel(),
                'latency_ms' => $health->latency_ms,
                'checks' => $health->checks(),
                'recommendation' => $health->recommendation(),
            ],
        ])->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<string, GlobalIntegrationHealth>  $healthIndex
     * @return list<array<string, mixed>>
     */
    protected function vaultRows($healthIndex, ?int $organizationId = null): array
    {
        $rows = [];
        $agentCredentialLabels = app(AiAgentRegistryService::class)->credentialLabelsByKey($organizationId);

        foreach (config('global_settings.vault_rpa', []) as $key => $meta) {
            $credential = $this->vault->get($key);
            $username = $credential?->username;
            $health = $healthIndex->get($key);

            $rows[] = [
                'key' => $key,
                'system' => $meta['label'],
                'login' => $username ? Str::limit($username, 40) : '—',
                'has_secret' => (bool) ($credential?->password || $credential?->api_key),
                'used_by' => $agentCredentialLabels[$key] ?? $meta['used_by'],
                'last_used' => $credential?->updated_at?->format('M j') ?? '—',
                'rotated' => $credential?->created_at?->format('M j') ?? '—',
                'test_status' => $health?->message,
                'health_badge' => $health?->statusBadgeClass(),
                'manage_url' => DirectoryIntegrationCatalog::credentialVaultUrl($key),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{when: string, actor: string, action: string}>
     */
    protected function auditPreview(): array
    {
        $entries = collect();

        PayrollAuditLog::query()
            ->withoutGlobalScopes()
            ->latest('occurred_at')
            ->limit(3)
            ->get()
            ->each(function (PayrollAuditLog $log) use ($entries) {
                $entries->push([
                    'when' => optional($log->occurred_at)->format('M j g:ia') ?? '—',
                    'actor' => $log->actor_name,
                    'action' => $log->detail ?: Str::headline(str_replace('_', ' ', $log->action)),
                    'sort' => $log->occurred_at ?? $log->created_at,
                ]);
            });

        if (class_exists(ActivityLog::class)) {
            ActivityLog::query()
                ->with('user')
                ->latest()
                ->limit(3)
                ->get()
                ->each(function (ActivityLog $log) use ($entries) {
                    $entries->push([
                        'when' => optional($log->created_at)->format('M j g:ia') ?? '—',
                        'actor' => $log->user?->name ?? 'System',
                        'action' => $log->description ?? Str::headline(str_replace('_', ' ', (string) $log->action)),
                        'sort' => $log->created_at,
                    ]);
                });
        }

        return $entries
            ->sortByDesc('sort')
            ->take(4)
            ->map(fn (array $row) => collect($row)->except('sort')->all())
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, CaregiverActivationCode>
     */
    protected function activationCodes(?Organization $organization)
    {
        if (! $organization) {
            return collect();
        }

        return CaregiverActivationCode::query()
            ->where('organization_id', $organization->id)
            ->with('employee')
            ->latest('issued_at')
            ->limit(20)
            ->get();
    }

    public static function maskTaxId(?string $taxId): string
    {
        if (! $taxId) {
            return '—';
        }

        $digits = preg_replace('/\D/', '', $taxId);

        if (strlen($digits) < 4) {
            return '••–••••••';
        }

        return '••–•••'.substr($digits, -4);
    }

    public static function formatAddress(?Organization $org): string
    {
        if (! $org) {
            return '';
        }

        $parts = array_filter([
            $org->legal_address_street,
            collect([$org->legal_address_city, $org->legal_address_state])->filter()->implode(', '),
            $org->legal_address_zip,
        ]);

        return implode(', ', $parts);
    }

    public static function stateLabel(?string $code): string
    {
        $states = config('global_settings.us_states', []);

        return $states[$code] ?? $code ?? 'Michigan';
    }
}

<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class GlobalSettingsService
{
    /**
     * @var array<string, array{group: string, default: mixed, label: string}>
     */
    public const DEFINITIONS = [
        'security.session_timeout_minutes' => [
            'group' => 'security',
            'default' => 30,
            'label' => 'Session Timeout (minutes)',
        ],
        'security.require_2fa' => [
            'group' => 'security',
            'default' => true,
            'label' => 'Require Two-Factor Authentication',
        ],
        'security.phi_access_logging' => [
            'group' => 'security',
            'default' => true,
            'label' => 'PHI Access Logging',
        ],
        'security.ip_restrictions' => [
            'group' => 'security',
            'default' => false,
            'label' => 'IP / Device Restrictions',
        ],
        'retention.document_retention_days' => [
            'group' => 'retention',
            'default' => 2555,
            'label' => 'Document Retention (days)',
        ],
        'billing.default_cycle' => [
            'group' => 'billing',
            'default' => 'monthly',
            'label' => 'Default Billing Cycle',
        ],
        'billing.default_asw_email' => [
            'group' => 'billing',
            'default' => null,
            'label' => 'Default ASW Email (DHS Home Help)',
        ],
        'billing.sigma_portal_url' => [
            'group' => 'billing',
            'default' => null,
            'label' => 'Sigma Portal URL Override',
        ],
        'flags.maintenance_mode' => [
            'group' => 'flags',
            'default' => false,
            'label' => 'Maintenance Mode',
        ],
        'uploads.max_file_size_kb' => [
            'group' => 'security',
            'default' => 10240,
            'label' => 'Maximum Upload Size (KB)',
        ],
        'programs.mich_hourly_rate' => [
            'group' => 'programs',
            'default' => 30.00,
            'label' => 'MICH Rate',
        ],
        'programs.dhs_hourly_rate' => [
            'group' => 'programs',
            'default' => 27.00,
            'label' => 'DHS Home Help Rate',
        ],
        'programs.default_caregiver_wage' => [
            'group' => 'programs',
            'default' => 15.00,
            'label' => 'Default Caregiver Wage',
        ],
        'programs.employment_type' => [
            'group' => 'programs',
            'default' => 'w2',
            'label' => 'Employment Type',
        ],
        'programs.pay_grace_days' => [
            'group' => 'programs',
            'default' => 10,
            'label' => 'Pay Grace Window (days)',
        ],
        'programs.batch_build_day' => [
            'group' => 'programs',
            'default' => 'first_tuesday',
            'label' => 'Batch Build Day',
        ],
        'programs.pay_day' => [
            'group' => 'programs',
            'default' => 'friday',
            'label' => 'Pay Day',
        ],
        'programs.roll_late_forms' => [
            'group' => 'programs',
            'default' => true,
            'label' => 'Roll Late Forms to Next Week',
        ],
        'access.signup_mode' => [
            'group' => 'access',
            'default' => 'invite_only',
            'label' => 'Caregiver App Sign-up Mode',
        ],
        'access.code_expiry_days' => [
            'group' => 'access',
            'default' => 7,
            'label' => 'Activation Code Expiry (days)',
        ],
        'access.bind_code_to_caregiver' => [
            'group' => 'access',
            'default' => true,
            'label' => 'Bind Code to Caregiver Record',
        ],
        'automation.miss_rate_ceiling' => [
            'group' => 'automation',
            'default' => 2.0,
            'label' => 'Miss-rate Ceiling (%)',
        ],
        'automation.default_autonomy' => [
            'group' => 'automation',
            'default' => 'approval_required',
            'label' => 'Default Autonomy for New Agents',
        ],
        'automation.approval_threshold' => [
            'group' => 'automation',
            'default' => 5000,
            'label' => 'Approval Threshold ($)',
        ],
        'automation.single_approver' => [
            'group' => 'automation',
            'default' => true,
            'label' => 'Route All Approvals to Single Approver',
        ],
        'automation.auto_submit_billing' => [
            'group' => 'automation',
            'default' => true,
            'label' => 'Auto-submit billing claims after monthly generation',
        ],
        'notifications.supported_languages' => [
            'group' => 'notifications',
            'default' => ['en', 'ar'],
            'label' => 'Supported Languages',
        ],
    ];

    protected ?array $runtimeCache = null;

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->runtime()[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function runtime(): array
    {
        if ($this->runtimeCache !== null) {
            return $this->runtimeCache;
        }

        return $this->runtimeCache = $this->all();
    }

    public function isMaintenanceModeEnabled(): bool
    {
        return (bool) $this->get('flags.maintenance_mode', false);
    }

    public function maxUploadKilobytes(): int
    {
        return (int) $this->get('uploads.max_file_size_kb', 10240);
    }

    public function defaultBillingCycle(): string
    {
        return (string) $this->get('billing.default_cycle', 'monthly');
    }

    public function isTwoFactorRequired(): bool
    {
        return (bool) $this->get('security.require_2fa', true);
    }

    public function documentRetentionDays(): int
    {
        return (int) $this->get('retention.document_retention_days', 2555);
    }

    public function michHourlyRate(): float
    {
        return (float) $this->get('programs.mich_hourly_rate', 30.00);
    }

    public function dhsHourlyRate(): float
    {
        return (float) $this->get('programs.dhs_hourly_rate', 27.00);
    }

    public function defaultCaregiverWage(): float
    {
        return (float) $this->get('programs.default_caregiver_wage', 15.00);
    }

    public function payGraceDays(): int
    {
        return (int) $this->get('programs.pay_grace_days', 10);
    }

    public function all(): array
    {
        $stored = Setting::query()
            ->whereIn('key', array_keys(self::DEFINITIONS))
            ->get()
            ->keyBy('key');

        $settings = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $settings[$key] = $stored->has($key)
                ? $stored[$key]->value_payload
                : $definition['default'];
        }

        return $settings;
    }

    public function forGroup(string $group): array
    {
        return collect($this->all())
            ->filter(fn ($value, $key) => self::DEFINITIONS[$key]['group'] === $group)
            ->all();
    }

    public function update(array $validated): void
    {
        $flat = Arr::dot($validated);

        foreach ($flat as $key => $value) {
            if (! array_key_exists($key, self::DEFINITIONS)) {
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'group' => self::DEFINITIONS[$key]['group'],
                    'value_payload' => $value,
                ]
            );
        }

        $this->runtimeCache = null;
    }

    public function definitionsForView(): Collection
    {
        return collect(self::DEFINITIONS);
    }

    public function retentionYears(): int
    {
        return (int) round($this->documentRetentionDays() / 365);
    }
}

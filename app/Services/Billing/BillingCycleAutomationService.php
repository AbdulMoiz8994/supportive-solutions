<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Services\AutomationActorService;
use App\Services\GlobalSettingsService;
use Carbon\Carbon;

/**
 * Monthly billing cycle automation (D2): scan eligible clients, generate
 * claims, and optionally auto-submit when enabled.
 */
class BillingCycleAutomationService
{
    public function __construct(
        protected BillingClaimGenerationService $generator,
        protected BillingClaimGenerateSubmitService $generateSubmit,
        protected GlobalSettingsService $settings,
        protected AutomationActorService $actors,
    ) {}

    /**
     * @return array{
     *     generated: int,
     *     refreshed: int,
     *     cp01_held: int,
     *     skipped: int,
     *     submitted: int,
     *     failed: int,
     *     availity: int,
     *     sigma_dhs: int,
     *     eligible: int
     * }
     */
    public function runForOrganization(int $organizationId, Carbon $period): array
    {
        $counts = $this->generator->generateForPeriod($organizationId, $period->copy());

        $result = array_merge($counts, [
            'submitted' => 0,
            'failed' => 0,
            'availity' => 0,
            'sigma_dhs' => 0,
            'eligible' => app(BillingEligibilityScanService::class)->eligibleCount($organizationId, $period),
        ]);

        if (! (bool) $this->settings->get('automation.auto_submit_billing', true)) {
            return $result;
        }

        if (($counts['cp01_held'] ?? 0) > 0) {
            return $result;
        }

        $actor = $this->actors->actorForOrganization($organizationId);

        if (! $actor) {
            return $result;
        }

        $submit = $this->generateSubmit->submitEligibleForPeriod($organizationId, $period->copy(), $actor);

        foreach (['submitted', 'failed', 'availity', 'sigma_dhs'] as $key) {
            $result[$key] = $submit[$key] ?? 0;
        }

        return $result;
    }

    /**
     * @return array{
     *     generated: int,
     *     refreshed: int,
     *     cp01_held: int,
     *     skipped: int,
     *     submitted: int,
     *     failed: int,
     *     availity: int,
     *     sigma_dhs: int,
     *     eligible: int
     * }
     */
    public function runAllOrganizations(Carbon $period, ?int $onlyOrganizationId = null): array
    {
        $orgIds = $onlyOrganizationId
            ? collect([$onlyOrganizationId])
            : Organization::query()->pluck('id');

        $totals = [
            'generated' => 0,
            'refreshed' => 0,
            'cp01_held' => 0,
            'skipped' => 0,
            'submitted' => 0,
            'failed' => 0,
            'availity' => 0,
            'sigma_dhs' => 0,
            'eligible' => 0,
        ];

        foreach ($orgIds as $orgId) {
            $counts = $this->runForOrganization((int) $orgId, $period->copy());

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + ($counts[$key] ?? 0);
            }
        }

        return $totals;
    }

    public function resolvePeriod(?string $periodYm): Carbon
    {
        if ($periodYm && preg_match('/^\d{4}-\d{2}$/', $periodYm)) {
            return Carbon::createFromFormat('Y-m', $periodYm)->startOfMonth();
        }

        return now()->subMonthNoOverflow()->startOfMonth();
    }
}

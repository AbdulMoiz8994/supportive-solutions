<?php

namespace App\Services\Billing;

use App\Models\BillingClaimAudit;
use App\Models\Client;
use App\Services\BillingClaimAuditWorkflowService;
use App\Services\GlobalSettingsService;
use App\Services\VisitReportService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BillingClaimGenerationService
{
    public function __construct(
        protected BillingClaimAuditWorkflowService $workflowService,
        protected BillingClaimCp01GateService $cp01Gate,
        protected GlobalSettingsService $settings,
        protected VisitReportService $visitReports,
        protected BillingEligibilityScanService $eligibilityScan,
    ) {}

    /**
     * @return array{generated: int, refreshed: int, cp01_held: int, skipped: int}
     */
    public function generateForPeriod(?int $organizationId, Carbon $period, ?int $userId = null): array
    {
        $counts = ['generated' => 0, 'refreshed' => 0, 'cp01_held' => 0, 'skipped' => 0];
        $periodStart = $period->copy()->startOfMonth();
        $periodEnd = $period->copy()->endOfMonth();

        $eligibleClients = $this->eligibilityScan
            ->scanEligibleClients($organizationId, $period)
            ->pluck('client');

        foreach ($eligibleClients as $client) {
            $result = $this->generateForClient($client, $period, $userId);
            $counts[$result]++;
        }

        return $counts;
    }

    /**
     * @return 'generated'|'refreshed'|'cp01_held'|'skipped'
     */
    public function generateForClient(Client $client, Carbon $period, ?int $userId = null): string
    {
        $periodStart = $period->copy()->startOfMonth();
        $periodEnd = $period->copy()->endOfMonth();

        $existing = BillingClaimAudit::withoutGlobalScopes()
            ->where('organization_id', $client->organization_id)
            ->where('client_id', $client->id)
            ->whereYear('billing_period', $period->year)
            ->whereMonth('billing_period', $period->month)
            ->first();

        if ($existing) {
            $this->workflowService->refreshAudit($existing);
            if ($this->cp01Gate->apply($existing, $period)) {
                $existing->updated_by = $userId;
                $existing->save();

                return 'cp01_held';
            }
            $existing->updated_by = $userId;
            $existing->save();

            return 'refreshed';
        }

        if (! $this->eligibilityScan->isClientEligible($client, $period)) {
            return 'skipped';
        }

        $hours = $this->completedHoursForClient($client, $periodStart, $periodEnd);

        if ($hours <= 0) {
            return 'skipped';
        }

        $program = $client->program_label === 'DHS'
            ? BillingClaimAudit::PROGRAM_DHS
            : BillingClaimAudit::PROGRAM_MICH;

        $rate = $program === BillingClaimAudit::PROGRAM_MICH
            ? ($client->billing_rate ?? $this->settings->michHourlyRate())
            : $this->settings->dhsHourlyRate();

        $audit = BillingClaimAudit::withoutGlobalScopes()->create(
            $this->baseAttributes($client, $program, $period, $periodStart, $periodEnd, $hours, (float) $rate, $userId)
        );

        $this->workflowService->refreshAudit($audit);

        if ($this->cp01Gate->apply($audit, $period)) {
            $audit->created_by = $userId;
            $audit->updated_by = $userId;
            $audit->save();

            return 'cp01_held';
        }

        $audit->billing_status = BillingClaimAudit::BILLING_READY;
        $audit->claim_status = BillingClaimAudit::STATUS_SUBMITTED;
        $audit->status_detail = 'Ready to submit';
        $audit->created_by = $userId;
        $audit->updated_by = $userId;
        $audit->save();

        return 'generated';
    }

    protected function completedHoursForClient(Client $client, Carbon $periodStart, Carbon $periodEnd): float
    {
        // Shared clean-visit gate (VisitReportService::hasCleanTimeData):
        // flagged visits — missing clock-out, off-site, impossible durations —
        // are excluded so they can never inflate a claim.
        return $this->visitReports->payableHours(
            $client->organization_id,
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
            $client->id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseAttributes(
        Client $client,
        string $program,
        Carbon $period,
        Carbon $periodStart,
        Carbon $periodEnd,
        float $hours,
        float $rate,
        ?int $userId
    ): array {
        $prefix = $program === BillingClaimAudit::PROGRAM_MICH ? '837P' : 'HH';
        $claimNumber = sprintf('%s-%s-%s', $prefix, $period->format('Y-m'), Str::upper(Str::random(4)));
        $employee = $client->primaryCaregiver ?? $client->employees->first();
        $amount = round($hours * $rate, 2);

        $shared = [
            'organization_id' => $client->organization_id,
            'client_id' => $client->id,
            'employee_id' => $employee?->id,
            'billing_period' => $periodStart->toDateString(),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'total_hours' => $hours,
            'hourly_rate' => $rate,
            'total_amount' => $amount,
            'medicaid_id' => $client->member_id,
            'audit_status' => BillingClaimAudit::AUDIT_NOT_REVIEWED,
            'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
            'billing_status' => BillingClaimAudit::BILLING_NOT_READY,
            'status_detail' => 'Generating',
            'lifecycle_events' => [],
            'documents' => [],
            'created_by' => $userId,
            'updated_by' => $userId,
        ];

        if ($program === BillingClaimAudit::PROGRAM_MICH) {
            return array_merge($shared, [
                'claim_number' => $claimNumber,
                'program_type' => BillingClaimAudit::PROGRAM_MICH,
                'service_code' => config('billing_claims_audit.standard_billing_code', 'T019'),
                'service_description' => 'Personal care services',
                'units' => (int) round($hours * 4),
                'submission_channel' => '837P - Availity',
                'billing_route' => 'availity_837p',
                'channel_subtext' => 'MCO',
                'payer_type' => 'MCO',
                'health_plan_name' => $client->coverageType?->plan_name ?? $client->mco_name,
                'plan_member_id' => $client->member_id,
            ]);
        }

        return array_merge($shared, [
            'claim_number' => $claimNumber,
            'program_type' => BillingClaimAudit::PROGRAM_DHS,
            'service_description' => 'Personal Care (+ scheduling)',
            'submission_channel' => 'Home Help - Sigma Portal',
            'billing_route' => 'sigma_portal',
            'billing_method' => 'email_asw',
            'channel_subtext' => 'MDHHS',
            'payer_type' => 'MDHHS',
            'health_plan_name' => $client->coverageType?->plan_name ?? 'DHS Home Help (Full Medicaid)',
            'authorization_description' => 'Time/Task Sheet',
        ]);
    }
}

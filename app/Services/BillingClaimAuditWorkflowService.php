<?php

namespace App\Services;

use App\Models\BillingClaimAudit;
use App\Models\CareDetail;
use App\Models\Client;
use App\Models\Schedule;
use App\Services\HHA\HHAExchangeClient;
use Carbon\Carbon;

class BillingClaimAuditWorkflowService
{
    public function __construct(
        protected HHAExchangeClient $hhaClient,
        protected \App\Services\Directory\IntegrationConnectionHealthRecorder $integrationHealth,
        protected VisitReportService $visitReports,
    ) {}

    public function calculateApprovedHoursFromUnits(int $units, int $unitMinutes = 15): float
    {
        if ($unitMinutes <= 0) {
            return 0;
        }

        return round(($units * $unitMinutes) / 60, 2);
    }

    public function calculateApprovedHoursFromT019Units(int $units): float
    {
        return round($units / 4, 2);
    }

    public function calculateDailyAverageHours(float $monthlyHours, Carbon $serviceMonth): float
    {
        $days = $serviceMonth->daysInMonth;

        return $days > 0 ? round($monthlyHours / $days, 2) : 0;
    }

    public function resolveAuthorizationStatus(?Carbon $start, ?Carbon $end): string
    {
        if (! $start && ! $end) {
            return BillingClaimAudit::AUTH_STATUS_MISSING;
        }

        if ($end && $end->isPast()) {
            return BillingClaimAudit::AUTH_STATUS_EXPIRED;
        }

        $threshold = (int) config('billing_claims_audit.expiring_authorization_days', 21);

        if ($end && $end->lte(now()->addDays($threshold))) {
            return BillingClaimAudit::AUTH_STATUS_EXPIRING_SOON;
        }

        return BillingClaimAudit::AUTH_STATUS_ACTIVE;
    }

    public function syncFromClient(BillingClaimAudit $audit): BillingClaimAudit
    {
        $client = Client::withoutGlobalScopes()
            ->with(['coverageType', 'careDetails', 'employees'])
            ->find($audit->client_id);

        if (! $client) {
            return $audit;
        }

        $audit->medicaid_id = $audit->medicaid_id ?? $client->member_id;
        $audit->plan_member_id = $audit->plan_member_id ?? $client->member_id;
        $audit->coverage_type = $audit->coverage_type ?? $client->coverageType?->name;
        $audit->health_plan_name = $audit->health_plan_name ?? $client->coverageType?->plan_name;
        $audit->payer_name = $audit->payer_name ?? $client->coverageType?->plan_name ?? $client->coverageType?->name;

        if (! $audit->employee_id && $client->primaryCaregiver) {
            $audit->employee_id = $client->primaryCaregiver->id;
        }

        $careDetail = $audit->care_detail_id
            ? CareDetail::withoutGlobalScopes()->find($audit->care_detail_id)
            : $client->currentAuthorization();

        if ($careDetail) {
            $audit->care_detail_id = $careDetail->id;
            $audit->authorization_number = $audit->authorization_number ?? $careDetail->billing_code;
            $audit->service_code = $audit->service_code ?? $careDetail->billing_code;
            $audit->authorization_start_date = $audit->authorization_start_date ?? $careDetail->start_date;
            $audit->authorization_valid_through = $audit->authorization_valid_through ?? $careDetail->end_date;
            $audit->approved_weekly_hours = $audit->approved_weekly_hours ?? $careDetail->hours_per_week;
            $audit->units = $audit->units ?? $careDetail->total_units;

            if ($careDetail->total_units) {
                $audit->calculated_approved_hours = $this->calculateApprovedHoursFromT019Units((int) $careDetail->total_units);
            }
        }

        return $audit;
    }

    public function syncVisitHours(BillingClaimAudit $audit): BillingClaimAudit
    {
        if ($audit->evv_exempt) {
            $audit->evv_status = BillingClaimAudit::EVV_EXEMPT;
            $audit->visit_verification_status = BillingClaimAudit::VISIT_VERIFIED;

            return $audit;
        }

        $schedules = Schedule::withoutGlobalScopes()
            ->where('organization_id', $audit->organization_id)
            ->where('client_id', $audit->client_id)
            ->when($audit->employee_id, fn ($q) => $q->where('employee_id', $audit->employee_id))
            ->whereBetween('date', [$audit->period_start, $audit->period_end])
            ->get();

        if ($schedules->isEmpty()) {
            $audit->evv_status = BillingClaimAudit::EVV_NOT_CONNECTED;
            $audit->visit_verification_status = BillingClaimAudit::VISIT_MISSING;

            return $audit;
        }

        $completed = $schedules->whereIn('status', [
            Schedule::STATUS_COMPLETED,
            'Verified',
        ]);
        $audit->scheduled_hours = round((float) $schedules->sum('total_hours'), 2);
        $audit->completed_visit_hours = round((float) $completed->sum('total_hours'), 2);
        $audit->verified_hours = round($completed
            ->filter(fn (Schedule $schedule) => $this->visitReports->hasCleanTimeData($schedule))
            ->sum(fn (Schedule $schedule) => (float) ($this->visitReports->effectiveHours($schedule) ?? 0)), 2);
        $audit->clock_in_verified = $completed->whereNotNull('actual_clock_in')->isNotEmpty();
        $audit->clock_out_verified = $completed->whereNotNull('actual_clock_out')->isNotEmpty();

        if ($completed->isEmpty()) {
            $audit->evv_status = BillingClaimAudit::EVV_PENDING;
            $audit->visit_verification_status = BillingClaimAudit::VISIT_PENDING;
        } else {
            $locallyVerified = $completed->filter(fn (Schedule $schedule) => $this->visitReports->hasCleanTimeData($schedule)
                && $schedule->actual_clock_in
                && $schedule->actual_clock_out);

            if ($this->hhaClient->isConnected()) {
                $audit->evv_status = $locallyVerified->count() === $completed->count()
                    ? BillingClaimAudit::EVV_PENDING_SYNC
                    : BillingClaimAudit::EVV_PENDING;
                $audit->visit_verification_status = $locallyVerified->isNotEmpty()
                    ? BillingClaimAudit::VISIT_PARTIAL
                    : BillingClaimAudit::VISIT_PENDING;
            } elseif ($locallyVerified->count() === $completed->count()) {
                $audit->evv_status = BillingClaimAudit::EVV_VERIFIED_LOCAL;
                $audit->visit_verification_status = BillingClaimAudit::VISIT_VERIFIED;
            } elseif ($locallyVerified->isNotEmpty()) {
                $audit->evv_status = BillingClaimAudit::EVV_PENDING;
                $audit->visit_verification_status = BillingClaimAudit::VISIT_PARTIAL;
            } else {
                $audit->evv_status = BillingClaimAudit::EVV_PENDING;
                $audit->visit_verification_status = BillingClaimAudit::VISIT_PENDING;
            }
        }

        if ($audit->total_hours === null && $audit->verified_hours > 0) {
            $audit->total_hours = $audit->verified_hours;
        }

        if ($this->hhaClient->isConnected()) {
            $this->integrationHealth->recordSync(\App\Models\IntegrationCredential::KEY_HHA);
        }

        return $audit;
    }

    public function recalculateFinancials(BillingClaimAudit $audit): BillingClaimAudit
    {
        $audit->recalculateTotalAmount();
        $audit->expected_amount = $audit->expected_amount ?? $audit->total_amount;

        $paid = (float) ($audit->paid_amount ?? 0);
        $billed = (float) ($audit->expected_amount ?? $audit->total_amount ?? 0);
        $adjustments = (float) ($audit->adjustment_amount ?? 0);
        $denials = (float) ($audit->denial_amount ?? 0);

        $audit->balance_amount = round(max($billed - $paid - $adjustments, 0), 2);
        $audit->payment_status = $this->resolvePaymentStatus($audit, $billed, $paid);

        return $audit;
    }

    public function resolvePaymentStatus(BillingClaimAudit $audit, float $billed, float $paid): string
    {
        if ($audit->denial_amount > 0 || $audit->billing_status === BillingClaimAudit::BILLING_DENIED) {
            return BillingClaimAudit::PAYMENT_DENIED;
        }

        if ($paid > 0) {
            if ($paid >= $billed) {
                return BillingClaimAudit::PAYMENT_PAID_FULL;
            }

            if ($paid >= ($billed * 0.5)) {
                return BillingClaimAudit::PAYMENT_PARTIAL;
            }

            return BillingClaimAudit::PAYMENT_UNDERPAID;
        }

        if (! $audit->eob_document_path && ! $audit->paid_at && in_array($audit->billing_status, [
            BillingClaimAudit::BILLING_SUBMITTED,
            BillingClaimAudit::BILLING_SENT,
            BillingClaimAudit::BILLING_PENDING_PAYMENT,
        ], true)) {
            return BillingClaimAudit::PAYMENT_MISSING_EOB;
        }

        if (in_array($audit->billing_status, [
            BillingClaimAudit::BILLING_SUBMITTED,
            BillingClaimAudit::BILLING_PENDING_PAYMENT,
        ], true)) {
            return BillingClaimAudit::PAYMENT_NOT_RECEIVED;
        }

        return BillingClaimAudit::PAYMENT_PENDING;
    }

    public function computeIssueFlags(BillingClaimAudit $audit): array
    {
        $flags = [];

        $authStatus = $audit->authorization_status ?? $this->resolveAuthorizationStatus(
            $audit->authorization_start_date ? Carbon::parse($audit->authorization_start_date) : null,
            $audit->authorization_valid_through ? Carbon::parse($audit->authorization_valid_through) : null,
        );

        match ($authStatus) {
            BillingClaimAudit::AUTH_STATUS_MISSING => $flags[] = 'missing_authorization',
            BillingClaimAudit::AUTH_STATUS_EXPIRED => $flags[] = 'expired_authorization',
            BillingClaimAudit::AUTH_STATUS_EXPIRING_SOON => $flags[] = 'expiring_authorization',
            default => null,
        };

        $approvedHours = (float) ($audit->calculated_approved_hours ?? 0);
        $billedHours = (float) ($audit->total_hours ?? 0);

        if ($approvedHours > 0 && $billedHours > $approvedHours) {
            $flags[] = 'over_authorized_hours';
        }

        if ($audit->visit_verification_status === BillingClaimAudit::VISIT_MISSING) {
            $flags[] = 'missing_visit_verification';
        }

        if ($audit->payment_status === BillingClaimAudit::PAYMENT_MISSING_EOB) {
            $flags[] = 'missing_eob';
        }

        if ($audit->payment_status === BillingClaimAudit::PAYMENT_UNDERPAID) {
            $flags[] = 'underpayment';
        }

        if ($audit->payment_status === BillingClaimAudit::PAYMENT_PARTIAL) {
            $flags[] = 'partial_payment';
        }

        if ($audit->payment_status === BillingClaimAudit::PAYMENT_DENIED) {
            $flags[] = 'denial';
        }

        if ($audit->override_reason) {
            $flags[] = 'manual_override';
        }

        if ($audit->audit_status === BillingClaimAudit::AUDIT_NEEDS_STAFF_REVIEW) {
            $flags[] = 'needs_staff_review';
        }

        return array_values(array_unique($flags));
    }

    public function evaluateBillingReadiness(BillingClaimAudit $audit): BillingClaimAudit
    {
        $flags = $this->computeIssueFlags($audit);
        $audit->issue_flags = $flags;
        $audit->authorization_status = $audit->authorization_status ?? $this->resolveAuthorizationStatus(
            $audit->authorization_start_date ? Carbon::parse($audit->authorization_start_date) : null,
            $audit->authorization_valid_through ? Carbon::parse($audit->authorization_valid_through) : null,
        );

        $blocking = array_intersect($flags, [
            'missing_authorization',
            'expired_authorization',
            'missing_visit_verification',
            'over_authorized_hours',
        ]);

        if (! empty($blocking) && ! $audit->override_reason) {
            $audit->billing_status = BillingClaimAudit::BILLING_BLOCKED;
            $audit->hold_reason = $audit->hold_reason ?? implode(', ', $blocking);
        } elseif ($audit->billing_status === BillingClaimAudit::BILLING_BLOCKED && empty($blocking)) {
            $audit->billing_status = BillingClaimAudit::BILLING_READY;
        }

        if (empty($audit->billing_status)) {
            $audit->billing_status = BillingClaimAudit::BILLING_NOT_READY;
        }

        $audit->syncClaimStatusFromBillingStatus();

        return $audit;
    }

    public function refreshAudit(BillingClaimAudit $audit): BillingClaimAudit
    {
        $this->syncFromClient($audit);
        $this->syncVisitHours($audit);

        if ($audit->approved_monthly_hours && $audit->billing_period) {
            $audit->calculated_daily_hours = $this->calculateDailyAverageHours(
                (float) $audit->approved_monthly_hours,
                Carbon::parse($audit->billing_period)
            );
        }

        if ($audit->units && ! $audit->calculated_approved_hours) {
            $unitMinutes = (int) ($audit->unit_minutes ?: config('billing_claims_audit.default_unit_minutes', 15));
            $code = strtoupper((string) $audit->service_code);
            $standardCodes = array_map('strtoupper', config('billing_claims_audit.standard_unit_codes', ['T019', 'T1019']));
            $audit->calculated_approved_hours = in_array($code, $standardCodes, true)
                ? $this->calculateApprovedHoursFromT019Units((int) $audit->units)
                : $this->calculateApprovedHoursFromUnits((int) $audit->units, $unitMinutes);
        }

        $this->recalculateFinancials($audit);
        $this->evaluateBillingReadiness($audit);

        return $audit;
    }

    public function appendActivity(BillingClaimAudit $audit, string $action, ?int $userId = null, ?string $detail = null): void
    {
        $log = $audit->activity_log ?? [];
        $log[] = [
            'action' => $action,
            'detail' => $detail,
            'user_id' => $userId,
            'at' => now()->toIso8601String(),
        ];
        $audit->activity_log = $log;
        $audit->last_action = $action;
    }

    public function applyOverride(BillingClaimAudit $audit, string $reason, int $userId): BillingClaimAudit
    {
        $audit->override_reason = $reason;
        $audit->overridden_by = $userId;
        $audit->overridden_at = now();

        if ($audit->billing_status === BillingClaimAudit::BILLING_BLOCKED) {
            $audit->billing_status = BillingClaimAudit::BILLING_READY;
        }

        $this->appendActivity($audit, 'Manual override applied', $userId, $reason);
        $this->evaluateBillingReadiness($audit);

        return $audit;
    }

    public function applyEobPayment(BillingClaimAudit $audit, array $data, ?int $userId = null): BillingClaimAudit
    {
        $audit->fill([
            'paid_amount' => $data['paid_amount'] ?? $audit->paid_amount,
            'payment_date' => $data['payment_date'] ?? $audit->payment_date,
            'adjustment_amount' => $data['adjustment_amount'] ?? $audit->adjustment_amount,
            'denial_amount' => $data['denial_amount'] ?? $audit->denial_amount,
            'rejection_reason' => $data['denial_reason'] ?? $audit->rejection_reason,
            'adjustment_reason' => $data['adjustment_reason'] ?? $audit->adjustment_reason,
            'payer_reference' => $data['payer_reference'] ?? $audit->payer_reference,
            'ai_extraction_status' => BillingClaimAudit::AI_NOT_CONNECTED,
            'ai_review_required' => true,
        ]);

        if (! empty($data['eob_document_path'])) {
            $audit->eob_document_path = $data['eob_document_path'];
        }

        $this->recalculateFinancials($audit);
        $this->evaluateBillingReadiness($audit);
        $this->appendActivity($audit, 'EOB / payment recorded', $userId, 'Manual payment entry');

        if ($audit->payment_status === BillingClaimAudit::PAYMENT_PAID_FULL) {
            $audit->billing_status = BillingClaimAudit::BILLING_PAID;
            $audit->paid_at = $audit->payment_date ?? now();
            $audit->status_detail = 'Paid · EOB posted';
        } elseif (in_array($audit->payment_status, [BillingClaimAudit::PAYMENT_PARTIAL, BillingClaimAudit::PAYMENT_UNDERPAID], true)) {
            $audit->billing_status = BillingClaimAudit::BILLING_UNDERPAID;
            $audit->audit_status = BillingClaimAudit::AUDIT_NEEDS_STAFF_REVIEW;
        } elseif ($audit->payment_status === BillingClaimAudit::PAYMENT_DENIED) {
            $audit->billing_status = BillingClaimAudit::BILLING_DENIED;
            $audit->audit_status = BillingClaimAudit::AUDIT_ISSUE_FOUND;
        }

        $audit->syncClaimStatusFromBillingStatus();

        return $audit;
    }
}

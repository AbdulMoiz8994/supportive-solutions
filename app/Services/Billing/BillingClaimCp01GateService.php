<?php

namespace App\Services\Billing;

use App\Models\BillingClaimAudit;
use Carbon\Carbon;

class BillingClaimCp01GateService
{
    /**
     * Apply CP-01 hold when the prior billing period has an outstanding balance.
     */
    public function apply(BillingClaimAudit $audit, Carbon $period): bool
    {
        if ($this->priorPeriodBlocksBilling($audit, $period)) {
            $audit->claim_status = BillingClaimAudit::STATUS_ON_HOLD;
            $audit->billing_status = BillingClaimAudit::BILLING_BLOCKED;
            $audit->hold_reason = 'CP-01 prior balance';
            $audit->status_detail = 'On hold (CP-01)';

            return true;
        }

        if (
            $audit->claim_status === BillingClaimAudit::STATUS_ON_HOLD
            && str_contains((string) $audit->hold_reason, 'CP-01')
            && ! $this->priorPeriodBlocksBilling($audit, $period)
        ) {
            $audit->hold_reason = null;
            $audit->billing_status = BillingClaimAudit::BILLING_READY;
            $audit->status_detail = 'Ready to submit';
            $audit->syncClaimStatusFromBillingStatus();
        }

        return false;
    }

    public function priorPeriodBlocksBilling(BillingClaimAudit $audit, Carbon $period): bool
    {
        $priorPeriod = $period->copy()->subMonth()->startOfMonth();

        $priorClaim = BillingClaimAudit::withoutGlobalScopes()
            ->where('organization_id', $audit->organization_id)
            ->where('client_id', $audit->client_id)
            ->whereYear('billing_period', $priorPeriod->year)
            ->whereMonth('billing_period', $priorPeriod->month)
            ->first();

        if (! $priorClaim) {
            return false;
        }

        if ($priorClaim->claim_status === BillingClaimAudit::STATUS_ON_HOLD) {
            return true;
        }

        $paid = (float) ($priorClaim->paid_amount ?? 0);
        $billed = (float) ($priorClaim->total_amount ?? 0);
        $effectiveStatus = $priorClaim->effectiveClaimStatus();

        if ($effectiveStatus === BillingClaimAudit::STATUS_PAID) {
            return false;
        }

        if (in_array($effectiveStatus, [
            BillingClaimAudit::STATUS_AWAITING_PAYMENT,
            BillingClaimAudit::STATUS_SUBMITTED,
            BillingClaimAudit::STATUS_REJECTED,
        ], true)) {
            return $billed > 0 && $paid < $billed;
        }

        return false;
    }

    public function countCp01HoldsForPeriod(?int $organizationId, Carbon $period): int
    {
        $query = BillingClaimAudit::query()
            ->whereYear('billing_period', $period->year)
            ->whereMonth('billing_period', $period->month)
            ->where('claim_status', BillingClaimAudit::STATUS_ON_HOLD)
            ->where('hold_reason', 'like', '%CP-01%');

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        return $query->count();
    }
}

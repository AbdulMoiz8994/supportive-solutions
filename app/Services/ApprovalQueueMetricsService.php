<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\BillingClaimAudit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Single source of truth for approval-queue billing metrics (A3).
 * Dashboard, Workflow Queues, Staff & AI Agents, sidebar badges, and the
 * Billing & Claims Audit page all derive headline counts and dollar totals from here.
 */
class ApprovalQueueMetricsService
{
    /**
     * Organization scope aligned with the Live Dashboard and Workflow Queues.
     * Super admins see all organisations (null); everyone else is org-scoped.
     */
    public function approvalOrganizationId(?User $user = null): ?int
    {
        $user ??= auth()->user();

        if (! $user) {
            return null;
        }

        return $user->isSuperAdmin() ? null : $user->organization_id;
    }

    /**
     * Pending invoice holds awaiting owner approval to send.
     */
    public function pendingBillingHoldCount(?int $organizationId, ?Carbon $period = null): int
    {
        return $this->pendingBillingHoldsQuery($organizationId, $period)->count();
    }

    /**
     * CP-01 / blocked claims on hold — same definition as the Billing page tab.
     */
    public function onHoldClaimCount(?int $organizationId, ?Carbon $period = null): int
    {
        return $this->onHoldClaims($organizationId, $period)->count();
    }

    /**
     * @return Collection<int, BillingClaimAudit>
     */
    public function onHoldClaims(?int $organizationId, ?Carbon $period = null): Collection
    {
        return $this->onHoldClaimsQuery($organizationId, $period)
            ->with('client')
            ->get()
            ->filter(fn (BillingClaimAudit $record) => $record->effectiveClaimStatus() === BillingClaimAudit::STATUS_ON_HOLD)
            ->values();
    }

    /**
     * Held claims for the approval queue: on-hold claims minus those already
     * represented by a pending invoice for the same client / billing period.
     *
     * @return Collection<int, BillingClaimAudit>
     */
    public function heldClaimsForApprovalQueue(?int $organizationId, ?Carbon $period = null): Collection
    {
        $pendingBillingWindows = $this->pendingBillingHoldsQuery($organizationId, $period)
            ->get(['client_id', 'period_start', 'period_end'])
            ->groupBy('client_id');

        return $this->onHoldClaims($organizationId, $period)
            ->reject(fn (BillingClaimAudit $claim) => $this->claimHasMatchingPendingBilling($claim, $pendingBillingWindows))
            ->values();
    }

    /**
     * @return array<string, int>
     */
    public function billingTabCounts(Collection $records): array
    {
        $countStatus = fn (string $status) => $records
            ->filter(fn (BillingClaimAudit $record) => $record->effectiveClaimStatus() === $status)
            ->count();

        return [
            'all' => $records->count(),
            'submitted' => $countStatus(BillingClaimAudit::STATUS_SUBMITTED),
            'on_hold' => $countStatus(BillingClaimAudit::STATUS_ON_HOLD),
            'awaiting_payment' => $countStatus(BillingClaimAudit::STATUS_AWAITING_PAYMENT),
            'paid' => $countStatus(BillingClaimAudit::STATUS_PAID),
            'rejected' => $countStatus(BillingClaimAudit::STATUS_REJECTED),
        ];
    }

    public function pendingBillingHoldsQuery(?int $organizationId, ?Carbon $period = null): Builder
    {
        $query = Billing::query()->where('status', 'Pending');

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        if ($period !== null) {
            $this->applyBillingPeriodScope($query, $period);
        }

        return $query;
    }

    /**
     * All client invoices whose service period overlaps the given month.
     */
    public function periodBillingsQuery(?int $organizationId, Carbon $period): Builder
    {
        $query = Billing::query();

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        $this->applyBillingPeriodScope($query, $period);

        return $query;
    }

    /**
     * Period-scoped billing dollar totals — shared by the Live Dashboard KPI strip,
     * Financial snapshot, and Billing & Claims Audit summary cards.
     *
     * Claim-audit rows are merged with unmatched client invoices so money tiles
     * stay aligned with billing holds on the approval queue.
     *
     * @return array{in_flight:int,outstanding_amount:float,billed_amount:float,collected_amount:float,supplemental_invoice_count:int}
     */
    public function periodBillingDollarStats(?int $organizationId, Carbon $period): array
    {
        $records = BillingClaimAudit::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereYear('billing_period', $period->year)
            ->whereMonth('billing_period', $period->month)
            ->get();

        $inFlight = $records->filter(function (BillingClaimAudit $record) {
            $status = $record->effectiveClaimStatus();

            return in_array($status, [
                BillingClaimAudit::STATUS_SUBMITTED,
                BillingClaimAudit::STATUS_AWAITING_PAYMENT,
                BillingClaimAudit::STATUS_ON_HOLD,
            ], true);
        });

        $paid = $records->filter(fn (BillingClaimAudit $record) => $record->effectiveClaimStatus() === BillingClaimAudit::STATUS_PAID);

        $invoiceStats = $this->supplementalInvoiceStats($organizationId, $period, $records);

        return [
            'in_flight' => $inFlight->count() + $invoiceStats['in_flight'],
            'outstanding_amount' => (float) $inFlight->sum(fn (BillingClaimAudit $record) => (float) ($record->balance_amount ?? max((float) $record->total_amount - (float) ($record->paid_amount ?? 0), 0)))
                + $invoiceStats['outstanding_amount'],
            'billed_amount' => (float) $records->sum('total_amount') + $invoiceStats['billed_amount'],
            'collected_amount' => (float) $paid->sum(fn (BillingClaimAudit $record) => (float) ($record->paid_amount ?? $record->total_amount))
                + $invoiceStats['collected_amount'],
            'supplemental_invoice_count' => $invoiceStats['invoice_count'],
        ];
    }

    /**
     * Invoice-side dollars for the period that are not already represented by a
     * claim-audit row (same invoice / client + overlapping service window).
     *
     * @param  Collection<int, BillingClaimAudit>  $claimRecords
     * @return array{billed_amount: float, collected_amount: float, outstanding_amount: float, in_flight: int, invoice_count: int}
     */
    public function supplementalInvoiceStats(?int $organizationId, Carbon $period, Collection $claimRecords): array
    {
        $invoices = $this->periodBillingsQuery($organizationId, $period)->get();

        $billed = 0.0;
        $collected = 0.0;
        $outstanding = 0.0;
        $inFlight = 0;
        $invoiceCount = 0;

        foreach ($invoices as $invoice) {
            if ($this->invoiceRepresentedByClaim($invoice, $claimRecords)) {
                continue;
            }

            $invoiceCount++;
            $amount = (float) $invoice->total_amount;
            $billed += $amount;

            if ($invoice->status === 'Paid') {
                $collected += $amount;

                continue;
            }

            if (in_array($invoice->status, ['Pending', 'Sent'], true)) {
                $outstanding += $amount;
                $inFlight++;
            }
        }

        return [
            'billed_amount' => $billed,
            'collected_amount' => $collected,
            'outstanding_amount' => $outstanding,
            'in_flight' => $inFlight,
            'invoice_count' => $invoiceCount,
        ];
    }

    /**
     * @param  Collection<int, BillingClaimAudit>  $claimRecords
     */
    public function invoiceRepresentedByClaim(Billing $invoice, Collection $claimRecords): bool
    {
        if ($invoice->invoice_number) {
            $byNumber = $claimRecords->first(function (BillingClaimAudit $claim) use ($invoice) {
                return $invoice->invoice_number === $claim->invoice_number
                    || $invoice->invoice_number === $claim->claim_number;
            });

            if ($byNumber) {
                return true;
            }
        }

        return $claimRecords->contains(fn (BillingClaimAudit $claim) => $this->billingMatchesClaimAudit($invoice, $claim));
    }

    public function billingMatchesClaimAudit(Billing $billing, BillingClaimAudit $claim): bool
    {
        if ($billing->client_id && $claim->client_id && (int) $billing->client_id !== (int) $claim->client_id) {
            return false;
        }

        if ($billing->invoice_number && (
            $billing->invoice_number === $claim->invoice_number
            || $billing->invoice_number === $claim->claim_number
        )) {
            return true;
        }

        if (! $claim->billing_period) {
            return (bool) ($billing->client_id && $claim->client_id && (int) $billing->client_id === (int) $claim->client_id);
        }

        if (! $billing->period_start || ! $billing->period_end) {
            return false;
        }

        $period = Carbon::parse($claim->billing_period);

        return Carbon::parse($billing->period_start)->lte($period->copy()->endOfMonth())
            && Carbon::parse($billing->period_end)->gte($period->copy()->startOfMonth());
    }

    public function onHoldClaimsQuery(?int $organizationId, ?Carbon $period = null): Builder
    {
        $query = BillingClaimAudit::query();

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        if ($period !== null) {
            $query->whereYear('billing_period', $period->year)
                ->whereMonth('billing_period', $period->month);
        }

        $this->applyEffectiveOnHoldFilter($query);

        return $query;
    }

    /**
     * @param  Collection<int|string, Collection<int, Billing>>  $pendingBillingWindows
     */
    public function claimHasMatchingPendingBilling(BillingClaimAudit $claim, Collection $pendingBillingWindows): bool
    {
        $candidates = $claim->client_id
            ? ($pendingBillingWindows->get($claim->client_id) ?? collect())
            : $pendingBillingWindows->flatten(1);

        return $candidates->contains(function (Billing $billing) use ($claim) {
            if (! $claim->billing_period) {
                return true;
            }

            if (! $billing->period_start || ! $billing->period_end) {
                return false;
            }

            $period = Carbon::parse($claim->billing_period);

            return Carbon::parse($billing->period_start)->lte($period->copy()->endOfMonth())
                && Carbon::parse($billing->period_end)->gte($period->copy()->startOfMonth());
        });
    }

    protected function applyBillingPeriodScope(Builder $query, Carbon $period): void
    {
        $start = $period->copy()->startOfMonth()->toDateString();
        $end = $period->copy()->endOfMonth()->toDateString();

        $query->where(function (Builder $inner) use ($start, $end) {
            $inner->whereBetween('period_start', [$start, $end])
                ->orWhereBetween('period_end', [$start, $end])
                ->orWhere(function (Builder $span) use ($start, $end) {
                    $span->where('period_start', '<=', $start)
                        ->where('period_end', '>=', $end);
                });
        });
    }

    protected function applyEffectiveOnHoldFilter(Builder $query): void
    {
        $billingStatuses = BillingClaimAudit::billingStatusesForClaimStatus(BillingClaimAudit::STATUS_ON_HOLD);
        $validStatuses = BillingClaimAudit::claimStatuses();

        $query->where(function (Builder $outer) use ($billingStatuses, $validStatuses) {
            $outer->where('claim_status', BillingClaimAudit::STATUS_ON_HOLD)
                ->whereIn('claim_status', $validStatuses);

            if ($billingStatuses !== []) {
                $outer->orWhere(function (Builder $derived) use ($billingStatuses, $validStatuses) {
                    $derived->whereIn('billing_status', $billingStatuses)
                        ->where(function (Builder $claim) use ($validStatuses) {
                            $claim->whereNull('claim_status')
                                ->orWhere('claim_status', '')
                                ->orWhereNotIn('claim_status', $validStatuses);
                        });
                });
            }
        });
    }
}

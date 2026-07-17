<?php

namespace App\Services;

use App\Models\BillingClaimAudit;
use App\Services\Billing\BillingEligibilityScanService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BillingClaimsAuditService
{
    public function __construct(
        protected BillingClaimAuditWorkflowService $workflowService,
        protected ApprovalQueueMetricsService $queueMetrics,
        protected GlobalSettingsService $settings,
        protected BillingEligibilityScanService $eligibilityScan,
    ) {}

    public function baseQuery(?int $organizationId = null): Builder
    {
        $query = BillingClaimAudit::query()->with(['client', 'employee', 'careDetail']);

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        return $query;
    }

    public function parsePeriod(?string $period): Carbon
    {
        if ($period && preg_match('/^\d{4}-\d{2}$/', $period)) {
            return Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        }

        return now()->subMonthNoOverflow()->startOfMonth();
    }

    public function applyPeriodScope(Builder $query, Carbon $period): Builder
    {
        return $query->whereYear('billing_period', $period->year)
            ->whereMonth('billing_period', $period->month);
    }

    public function filteredQuery(?int $organizationId, array $filters = [], bool $applySorting = true): Builder
    {
        $query = $this->baseQuery($organizationId);

        $period = $this->parsePeriod($filters['period'] ?? null);
        $this->applyPeriodScope($query, $period);

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('claim_number', 'like', '%'.$search.'%')
                    ->orWhere('invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('payer_name', 'like', '%'.$search.'%')
                    ->orWhere('plan_member_id', 'like', '%'.$search.'%')
                    ->orWhere('medicaid_id', 'like', '%'.$search.'%')
                    ->orWhereHas('client', function (Builder $clientQuery) use ($search) {
                        $clientQuery->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%')
                            ->orWhere('member_id', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('employee', function (Builder $empQuery) use ($search) {
                        $empQuery->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%');
                    });
            });
        }

        if (! empty($filters['program']) && in_array($filters['program'], BillingClaimAudit::programTypes(), true)) {
            $query->where('program_type', $filters['program']);
        }

        if (! empty($filters['status']) && in_array($filters['status'], BillingClaimAudit::claimStatuses(), true)) {
            $this->applyEffectiveClaimStatusFilter($query, $filters['status']);
        }

        if (! empty($filters['billing_status']) && in_array($filters['billing_status'], BillingClaimAudit::billingStatuses(), true)) {
            $query->where('billing_status', $filters['billing_status']);
        }

        if (! empty($filters['audit_status']) && in_array($filters['audit_status'], BillingClaimAudit::auditStatuses(), true)) {
            $query->where('audit_status', $filters['audit_status']);
        }

        if (! empty($filters['authorization_status']) && in_array($filters['authorization_status'], BillingClaimAudit::authorizationStatuses(), true)) {
            $query->where('authorization_status', $filters['authorization_status']);
        }

        $coverageTypes = config('billing_claims_audit.coverage_types', []);
        if (! empty($filters['coverage_type']) && (empty($coverageTypes) || in_array($filters['coverage_type'], $coverageTypes, true))) {
            $query->where('coverage_type', $filters['coverage_type']);
        }

        if (! empty($filters['payment_status']) && in_array($filters['payment_status'], BillingClaimAudit::paymentStatuses(), true)) {
            $query->where('payment_status', $filters['payment_status']);
        }

        $issueTypes = config('billing_claims_audit.issue_flag_types', []);
        if (! empty($filters['issue_type']) && (empty($issueTypes) || in_array($filters['issue_type'], $issueTypes, true))) {
            $query->whereJsonContains('issue_flags', $filters['issue_type']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', (int) $filters['client_id']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        if ($applySorting) {
            $sort = $filters['sort'] ?? 'status';
            match ($sort) {
                'client' => $query->join('clients', 'clients.id', '=', 'billing_claim_audits.client_id')
                    ->orderBy('clients.last_name')
                    ->orderBy('clients.first_name')
                    ->select('billing_claim_audits.*'),
                'amount' => $query->orderByDesc('total_amount'),
                'period' => $query->orderByDesc('billing_period'),
                default => $query->orderBy('claim_status')->orderByDesc('submitted_at'),
            };
        }

        return $query;
    }

    public function paginate(?int $organizationId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filteredQuery($organizationId, $filters)->paginate($perPage)->withQueryString();
    }

    public function summaryForPeriod(?int $organizationId, Carbon $period): array
    {
        $records = $this->applyPeriodScope($this->baseQuery($organizationId), $period)->get();

        $countByStatus = fn (string $status) => $records
            ->filter(fn (BillingClaimAudit $record) => $record->effectiveClaimStatus() === $status)
            ->count();

        $dollarStats = $this->queueMetrics->periodBillingDollarStats($organizationId, $period);

        $awaiting = $records
            ->filter(fn (BillingClaimAudit $record) => $record->effectiveClaimStatus() === BillingClaimAudit::STATUS_AWAITING_PAYMENT)
            ->sum('total_amount');

        $periodEnd = $period->copy()->endOfMonth();
        $ytdStart = $period->copy()->startOfYear();
        $ytdBilled = $this->baseQuery($organizationId)
            ->whereBetween('billing_period', [$ytdStart->toDateString(), $periodEnd->toDateString()])
            ->sum('total_amount');

        $workflow = [
            'ready_to_bill' => $records->where('billing_status', BillingClaimAudit::BILLING_READY)->count(),
            'blocked' => $records->where('billing_status', BillingClaimAudit::BILLING_BLOCKED)->count(),
            'sent_submitted' => $records->whereIn('billing_status', [
                BillingClaimAudit::BILLING_SENT,
                BillingClaimAudit::BILLING_SUBMITTED,
            ])->count(),
            'pending_payment' => $records->where('billing_status', BillingClaimAudit::BILLING_PENDING_PAYMENT)->count(),
            'underpaid_denied' => $records->whereIn('billing_status', [
                BillingClaimAudit::BILLING_UNDERPAID,
                BillingClaimAudit::BILLING_DENIED,
            ])->count(),
            'missing_eob' => $records->where('payment_status', BillingClaimAudit::PAYMENT_MISSING_EOB)->count(),
            'expiring_auth' => $records->where('authorization_status', BillingClaimAudit::AUTH_STATUS_EXPIRING_SOON)->count(),
            'total_paid' => $records->sum(fn ($r) => (float) ($r->paid_amount ?? 0)),
            'outstanding_balance' => $dollarStats['outstanding_amount'],
        ];

        $eligibleCount = $this->eligibilityScan->eligibleCount($organizationId, $period);

        $autoGeneratedCount = $records
            ->filter(function (BillingClaimAudit $record) {
                if ($record->submitted_at === null) {
                    return false;
                }

                if ($record->effectiveClaimStatus() === BillingClaimAudit::STATUS_ON_HOLD) {
                    return false;
                }

                // Automated runs leave created_by null; manual "Generate & submit" sets a user id.
                return $record->created_by === null;
            })
            ->count();

        return [
            'period' => $period,
            'total_count' => $records->count(),
            'billed_amount' => $dollarStats['billed_amount'],
            'billed_count' => $records->count() + $dollarStats['supplemental_invoice_count'],
            'paid_amount' => $dollarStats['collected_amount'],
            'paid_count' => $countByStatus(BillingClaimAudit::STATUS_PAID),
            'awaiting_amount' => $awaiting,
            'awaiting_count' => $countByStatus(BillingClaimAudit::STATUS_AWAITING_PAYMENT),
            'on_hold_count' => $this->queueMetrics->onHoldClaimCount($organizationId, $period),
            'rejected_count' => $countByStatus(BillingClaimAudit::STATUS_REJECTED),
            'submitted_count' => $countByStatus(BillingClaimAudit::STATUS_SUBMITTED),
            'ytd_billed' => $ytdBilled,
            'ytd_billed_label' => BillingClaimAudit::formatYtdAmount((float) $ytdBilled),
            'eligible_count' => $eligibleCount,
            'auto_generated_count' => min($autoGeneratedCount, $eligibleCount),
            'auto_billing_on' => (bool) $this->settings->get('automation.auto_submit_billing', true),
            'workflow' => $workflow,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function tabCounts(?int $organizationId, array $filters): array
    {
        $filtersWithoutStatus = $filters;
        unset($filtersWithoutStatus['status']);

        $records = $this->filteredQuery($organizationId, $filtersWithoutStatus, applySorting: false)->get();

        return $this->buildTabCounts($records);
    }

    /**
     * @return array<string, int>
     */
    protected function buildTabCounts(Collection $records): array
    {
        return $this->queueMetrics->billingTabCounts($records);
    }

    protected function applyEffectiveClaimStatusFilter(Builder $query, string $status): void
    {
        $validStatuses = BillingClaimAudit::claimStatuses();
        $billingStatuses = BillingClaimAudit::billingStatusesForClaimStatus($status);

        $query->where(function (Builder $q) use ($status, $billingStatuses, $validStatuses) {
            $q->where(function (Builder $explicit) use ($status, $validStatuses) {
                $explicit->where('claim_status', $status)
                    ->whereIn('claim_status', $validStatuses);
            });

            if ($billingStatuses !== []) {
                $q->orWhere(function (Builder $derived) use ($billingStatuses, $validStatuses) {
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

    public function periodOptions(Carbon $selected): Collection
    {
        return collect(range(0, 2))->map(function ($offset) use ($selected) {
            $date = $selected->copy()->subMonths($offset);

            return [
                'value' => $date->format('Y-m'),
                'label' => $date->format('M Y'),
            ];
        });
    }

    public function adjacentPeriod(Carbon $period, int $direction): Carbon
    {
        return $period->copy()->addMonths($direction)->startOfMonth();
    }

    protected function outstandingQuery(?int $organizationId, Carbon $asOf, ?string $program = null): Builder
    {
        $query = $this->baseQuery($organizationId)
            ->whereIn('claim_status', [
                BillingClaimAudit::STATUS_SUBMITTED,
                BillingClaimAudit::STATUS_AWAITING_PAYMENT,
            ])
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<=', $asOf->copy()->endOfDay());

        if ($program && in_array($program, BillingClaimAudit::programTypes(), true)) {
            $query->where('program_type', $program);
        }

        return $query;
    }

    public function outstandingRecords(?int $organizationId, Carbon $asOf, ?string $program = null): Collection
    {
        return $this->outstandingQuery($organizationId, $asOf, $program)
            ->with('client')
            ->get();
    }

    public function agingData(?int $organizationId, Carbon $asOf, ?string $program = null): array
    {
        $records = $this->outstandingQuery($organizationId, $asOf, $program)->get();

        $buckets = [
            'current' => ['amount' => 0, 'count' => 0],
            '31_60' => ['amount' => 0, 'count' => 0],
            '61_90' => ['amount' => 0, 'count' => 0],
            '90_plus' => ['amount' => 0, 'count' => 0],
        ];

        foreach ($records as $record) {
            $bucket = $record->agingBucket($asOf) ?? 'current';
            $buckets[$bucket]['amount'] += (float) $record->total_amount;
            $buckets[$bucket]['count']++;
        }

        $totalOutstanding = array_sum(array_column($buckets, 'amount'));
        $totalCount = array_sum(array_column($buckets, 'count'));

        $byChannel = $records->groupBy(fn ($r) => $r->program_type.'|'.$r->submission_channel)
            ->map(function (Collection $group, string $key) use ($asOf) {
                [$programType, $channel] = explode('|', $key, 2);
                $row = [
                    'program_type' => $programType,
                    'channel' => $channel,
                    'current' => 0,
                    '31_60' => 0,
                    '61_90' => 0,
                    '90_plus' => 0,
                    'total' => 0,
                ];

                foreach ($group as $record) {
                    $bucket = $record->agingBucket($asOf) ?? 'current';
                    $row[$bucket] += (float) $record->total_amount;
                    $row['total'] += (float) $record->total_amount;
                }

                return $row;
            })
            ->values()
            ->sortByDesc('total');

        $overdue = $records->filter(fn ($r) => ($r->ageInDays($asOf) ?? 0) > 30)
            ->sortByDesc(fn ($r) => $r->ageInDays($asOf))
            ->values();

        return [
            'buckets' => $buckets,
            'total_outstanding' => $totalOutstanding,
            'total_count' => $totalCount,
            'by_channel' => $byChannel,
            'overdue_total' => $overdue->count(),
            'overdue' => $overdue,
        ];
    }

    public function overduePaginated(?int $organizationId, Carbon $asOf, ?string $program, int $perPage = 5): LengthAwarePaginator
    {
        return $this->outstandingQuery($organizationId, $asOf, $program)
            ->where('submitted_at', '<=', $asOf->copy()->endOfDay()->subDays(31))
            ->orderBy('submitted_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function escalateOverdueClaims(?int $organizationId, Carbon $asOf, ?string $program, int $userId): int
    {
        $claims = $this->outstandingQuery($organizationId, $asOf, $program)->get()
            ->filter(fn ($r) => ($r->ageInDays($asOf) ?? 0) > 30);

        $count = 0;

        foreach ($claims as $claim) {
            if ($claim->audit_status === BillingClaimAudit::AUDIT_NOT_REVIEWED) {
                $claim->update([
                    'audit_status' => BillingClaimAudit::AUDIT_ESCALATED,
                    'updated_by' => $userId,
                ]);
                $count++;
            }
        }

        return $count;
    }

    public function updateRate(BillingClaimAudit $audit, float $rate, int $userId): BillingClaimAudit
    {
        $audit->hourly_rate = $rate;
        $this->workflowService->recalculateFinancials($audit);

        if ($audit->claim_status === BillingClaimAudit::STATUS_PAID) {
            $audit->paid_amount = $audit->total_amount;
        }

        $audit->updated_by = $userId;
        $this->workflowService->appendActivity($audit, 'Rate updated', $userId);
        $audit->save();

        return $audit->fresh(['client', 'employee', 'careDetail']);
    }

    public function refreshRecord(BillingClaimAudit $audit, ?int $userId = null): BillingClaimAudit
    {
        $this->workflowService->refreshAudit($audit);
        if ($userId) {
            $audit->updated_by = $userId;
        }
        $audit->save();

        return $audit->fresh(['client', 'employee', 'careDetail']);
    }

    public function recordEobPayment(BillingClaimAudit $audit, array $data, int $userId): BillingClaimAudit
    {
        $this->workflowService->applyEobPayment($audit, $data, $userId);
        $audit->updated_by = $userId;
        $audit->save();

        return $audit->fresh(['client', 'employee', 'careDetail']);
    }

    public function applyOverride(BillingClaimAudit $audit, string $reason, int $userId): BillingClaimAudit
    {
        $this->workflowService->applyOverride($audit, $reason, $userId);
        $audit->updated_by = $userId;
        $audit->save();

        return $audit->fresh(['client', 'employee', 'careDetail']);
    }
}

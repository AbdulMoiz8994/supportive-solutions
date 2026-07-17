<?php

namespace App\Services\Billing;

use App\Models\BillingClaimAudit;
use App\Services\Availity\AvailityClaimStatusMapper;
use App\Services\Availity\AvailityClient;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class BillingClaimAvailityStatusService
{
    public function __construct(
        protected AvailityClient $availityClient,
        protected AvailityClaimStatusMapper $statusMapper,
        protected BillingClaimSubmissionService $submissionService,
    ) {}

    public function canSync(BillingClaimAudit $audit): bool
    {
        return $this->submissionService->shouldSubmitViaAvaility($audit);
    }

    /**
     * @return array{success: bool, status: string, reference_id: ?string, raw: array, message: ?string}
     */
    public function sync(BillingClaimAudit $audit, ?int $userId = null): array
    {
        if (! $this->canSync($audit)) {
            throw new InvalidArgumentException('This claim is not routed through Availity.');
        }

        $result = null;

        if ($audit->availity_reference_id) {
            $professional = $this->syncViaProfessionalClaim($audit);
            if ($professional['success']) {
                $result = $professional;
            }
        }

        if ($result === null) {
            $result = $this->availityClient->inquireClaimStatus(
                $this->statusMapper->fromBillingClaimAudit($audit)
            );
        }

        $this->applyToAudit($audit, $result, $userId);

        Log::channel('availity')->info('Billing claim Availity status synced', [
            'audit_id' => $audit->id,
            'success' => $result['success'],
            'status' => $result['status'],
        ]);

        return $result;
    }

    /**
     * @return array{synced: int, failed: int, skipped: int}
     */
    public function syncPeriod(?int $organizationId, \Carbon\Carbon $period, ?int $userId = null): array
    {
        $query = BillingClaimAudit::query()
            ->whereYear('billing_period', $period->year)
            ->whereMonth('billing_period', $period->month)
            ->where('program_type', BillingClaimAudit::PROGRAM_MICH)
            ->where(function ($q) {
                $q->where('submission_channel', 'like', '%availity%')
                    ->orWhere('billing_route', 'availity_837p');
            })
            ->whereNotNull('submitted_at');

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        $counts = ['synced' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($query->get() as $audit) {
            if (! $this->canSync($audit)) {
                $counts['skipped']++;

                continue;
            }

            try {
                $result = $this->sync($audit, $userId);
                $result['success'] ? $counts['synced']++ : $counts['failed']++;
            } catch (\Throwable $exception) {
                $counts['failed']++;
                Log::channel('availity')->warning('Batch Availity status sync failed for audit', [
                    'audit_id' => $audit->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $counts;
    }

    /**
     * @return array{success: bool, status: string, reference_id: ?string, raw: array, message: ?string}
     */
    protected function syncViaProfessionalClaim(BillingClaimAudit $audit): array
    {
        $result = $this->availityClient->checkClaimStatus((string) $audit->availity_reference_id);

        return [
            'success' => $result['success'],
            'status' => $result['status'],
            'reference_id' => $result['claim_id'] ?? $audit->availity_reference_id,
            'raw' => $result['raw'],
            'message' => $result['raw']['message'] ?? null,
        ];
    }

    /**
     * @param  array{success: bool, status: string, reference_id: ?string, raw: array, message?: ?string}  $result
     */
    protected function applyToAudit(BillingClaimAudit $audit, array $result, ?int $userId): void
    {
        $updates = [
            'availity_status' => $result['status'],
            'availity_status_payload' => $result['raw'],
            'availity_status_checked_at' => now(),
        ];

        if ($userId !== null) {
            $updates['updated_by'] = $userId;
        }

        if (! empty($result['reference_id'])) {
            $updates['availity_reference_id'] = $result['reference_id'];
        }

        if ($result['success']) {
            $this->mapAvailityStatusToBillingFields($audit, $result, $updates);
        }

        $audit->fill($updates);
        if (isset($updates['billing_status'])) {
            $audit->syncClaimStatusFromBillingStatus();
            $updates['claim_status'] = $audit->claim_status;
        }

        $audit->update($updates);
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  array{success: bool, status: string, raw: array}  $result
     */
    protected function mapAvailityStatusToBillingFields(BillingClaimAudit $audit, array $result, array &$updates): void
    {
        $normalized = strtolower($result['status']);

        if (in_array($normalized, ['approved', 'paid', 'accepted'], true)) {
            $updates['billing_status'] = BillingClaimAudit::BILLING_PAID;
            $updates['payment_status'] = BillingClaimAudit::PAYMENT_PAID_FULL;
        } elseif (in_array($normalized, ['rejected', 'denied'], true)) {
            $updates['billing_status'] = BillingClaimAudit::BILLING_DENIED;
            $updates['payment_status'] = BillingClaimAudit::PAYMENT_DENIED;
        } elseif (in_array($normalized, ['pending', 'processing', 'submitted'], true)) {
            $updates['billing_status'] = BillingClaimAudit::BILLING_PENDING_PAYMENT;
            $updates['payment_status'] = BillingClaimAudit::PAYMENT_PENDING;
        }

        $paidAmount = $this->extractPaidAmount($result['raw']);
        if ($paidAmount !== null && $paidAmount > 0) {
            $updates['paid_amount'] = $paidAmount;
            $updates['balance_amount'] = max((float) ($audit->total_amount ?? 0) - $paidAmount, 0);
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function extractPaidAmount(array $raw): ?float
    {
        $statuses = $raw['claimStatuses'] ?? [];
        $first = is_array($statuses[0] ?? null) ? $statuses[0] : [];

        foreach (['paidAmount', 'paymentAmount', 'claimPaymentAmount', 'amountPaid'] as $key) {
            if (isset($first[$key]) && is_numeric($first[$key])) {
                return (float) $first[$key];
            }
        }

        return null;
    }
}

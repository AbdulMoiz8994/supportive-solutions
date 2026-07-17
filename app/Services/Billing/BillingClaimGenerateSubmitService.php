<?php

namespace App\Services\Billing;

use App\Models\BillingClaimAudit;
use App\Models\IntegrationCredential;
use App\Models\User;
use App\Services\BillingClaimsAuditService;
use App\Services\Directory\IntegrationConnectionHealthRecorder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BillingClaimGenerateSubmitService
{
    public function __construct(
        protected BillingClaimsAuditService $auditService,
        protected BillingClaimGenerationService $generationService,
        protected BillingClaimCp01GateService $cp01Gate,
        protected BillingClaimChannelSubmissionService $channelSubmissionService,
        protected IntegrationConnectionHealthRecorder $integrationHealth,
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     flash: string,
     *     flash_type: string,
     *     generated: int,
     *     refreshed: int,
     *     submitted: int,
     *     failed: int,
     *     availity: int,
     *     sigma_dhs: int,
     *     skipped: int,
     *     errors: list<string>
     * }
     */
    public function run(?int $organizationId, Carbon $period, User $user): array
    {
        $generation = $this->generationService->generateForPeriod($organizationId, $period, $user->id);

        $onHold = $this->cp01Gate->countCp01HoldsForPeriod($organizationId, $period);

        if ($onHold > 0) {
            return [
                'success' => false,
                'flash' => "{$onHold} bills remain on hold (CP-01). Review prior balances before submitting.",
                'flash_type' => 'warning',
                'generated' => $generation['generated'],
                'refreshed' => $generation['refreshed'],
                'submitted' => 0,
                'failed' => 0,
                'availity' => 0,
                'sigma_dhs' => 0,
                'skipped' => $generation['skipped'],
                'errors' => [],
            ];
        }

        $claims = $this->eligibleClaims($organizationId, $period);
        $submitted = 0;
        $failed = 0;
        $availity = 0;
        $sigmaDhs = 0;
        $errors = [];

        foreach ($claims as $claim) {
            $result = $this->channelSubmissionService->submit($claim, $user);

            if ($result['success']) {
                $submitted++;
                if ($result['channel'] === BillingClaimChannelSubmissionService::CHANNEL_AVAILITY) {
                    $availity++;
                }
                if ($result['channel'] === BillingClaimChannelSubmissionService::CHANNEL_SIGMA_DHS) {
                    $sigmaDhs++;
                }
            } else {
                $failed++;
                $errors[] = ($claim->claim_number ?? 'Claim #'.$claim->id).': '.$result['message'];
            }
        }

        if ($availity > 0) {
            $this->integrationHealth->recordBatch(IntegrationCredential::KEY_AVAILITY);
        }

        if ($sigmaDhs > 0) {
            $this->integrationHealth->recordBatch(IntegrationCredential::KEY_SIGMA);
        }

        return [
            'success' => $submitted > 0 || ($generation['generated'] > 0 && $failed === 0),
            'flash' => $this->buildFlashMessage($generation, $submitted, $failed, $availity, $sigmaDhs, $claims->count()),
            'flash_type' => $this->resolveFlashType($submitted, $failed, $claims->count(), $generation),
            'generated' => $generation['generated'],
            'refreshed' => $generation['refreshed'],
            'submitted' => $submitted,
            'failed' => $failed,
            'availity' => $availity,
            'sigma_dhs' => $sigmaDhs,
            'skipped' => $generation['skipped'],
            'errors' => $errors,
        ];
    }

    /**
     * @return array{success: bool, flash: string, flash_type: string}
     */
    public function submitSingle(BillingClaimAudit $claim, User $user): array
    {
        if (
            $claim->submitted_at !== null
            && ! $claim->isDhs()
        ) {
            return [
                'success' => false,
                'flash' => 'This claim was already submitted on '.$claim->submitted_at->format('M j, Y g:i A').'.',
                'flash_type' => 'warning',
            ];
        }

        if (
            $claim->claim_status === BillingClaimAudit::STATUS_ON_HOLD
            && str_contains((string) $claim->hold_reason, 'CP-01')
        ) {
            return [
                'success' => false,
                'flash' => 'This claim is on hold (CP-01). Clear the prior-period balance before submitting.',
                'flash_type' => 'warning',
            ];
        }

        if ($claim->isDhs() && $claim->submitted_at !== null) {
            $claim->submitted_at = null;
            $claim->save();
        }

        $result = $this->channelSubmissionService->submit($claim, $user);

        if ($result['channel'] === BillingClaimChannelSubmissionService::CHANNEL_AVAILITY && $result['success']) {
            $this->integrationHealth->recordBatch(IntegrationCredential::KEY_AVAILITY);
        }

        if ($result['channel'] === BillingClaimChannelSubmissionService::CHANNEL_SIGMA_DHS && $result['success']) {
            $this->integrationHealth->recordBatch(IntegrationCredential::KEY_SIGMA);
        }

        return [
            'success' => $result['success'],
            'flash' => $result['message'],
            'flash_type' => $result['success'] ? 'success' : 'warning',
        ];
    }

    /**
     * Submit all eligible claims for a period — used by the monthly scheduler.
     *
     * @return array{submitted: int, failed: int, availity: int, sigma_dhs: int, errors: list<string>}
     */
    public function submitEligibleForPeriod(?int $organizationId, Carbon $period, User $user): array
    {
        $claims = $this->eligibleClaims($organizationId, $period);
        $submitted = 0;
        $failed = 0;
        $availity = 0;
        $sigmaDhs = 0;
        $errors = [];

        foreach ($claims as $claim) {
            $result = $this->channelSubmissionService->submit($claim, $user);

            if ($result['success']) {
                $submitted++;
                if ($result['channel'] === BillingClaimChannelSubmissionService::CHANNEL_AVAILITY) {
                    $availity++;
                }
                if ($result['channel'] === BillingClaimChannelSubmissionService::CHANNEL_SIGMA_DHS) {
                    $sigmaDhs++;
                }
            } else {
                $failed++;
                $errors[] = ($claim->claim_number ?? 'Claim #'.$claim->id).': '.$result['message'];
            }
        }

        if ($availity > 0) {
            $this->integrationHealth->recordBatch(IntegrationCredential::KEY_AVAILITY);
        }

        if ($sigmaDhs > 0) {
            $this->integrationHealth->recordBatch(IntegrationCredential::KEY_SIGMA);
        }

        return [
            'submitted' => $submitted,
            'failed' => $failed,
            'availity' => $availity,
            'sigma_dhs' => $sigmaDhs,
            'errors' => $errors,
        ];
    }

    /**
     * @return Collection<int, BillingClaimAudit>
     */
    protected function eligibleClaims(?int $organizationId, Carbon $period): Collection
    {
        $query = BillingClaimAudit::query();

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        $this->auditService->applyPeriodScope($query, $period);

        $this->applySubmissionEligibilityScope($query);

        return $query->get();
    }

    protected function applySubmissionEligibilityScope(Builder $query): Builder
    {
        return $query->whereNull('submitted_at')
            ->where(function (Builder $q) {
                $q->where('claim_status', BillingClaimAudit::STATUS_SUBMITTED)
                    ->orWhereIn('billing_status', [
                        BillingClaimAudit::BILLING_READY,
                        BillingClaimAudit::BILLING_SUBMITTED,
                        BillingClaimAudit::BILLING_SENT,
                    ]);
            })
            ->where(function (Builder $q) {
                $q->where('claim_status', '!=', BillingClaimAudit::STATUS_ON_HOLD)
                    ->orWhereNull('claim_status');
            })
            ->where(function (Builder $q) {
                $q->whereNull('hold_reason')
                    ->orWhere('hold_reason', 'not like', '%CP-01%');
            });
    }

    /**
     * @param  array{generated: int, refreshed: int, skipped: int}  $generation
     */
    protected function buildFlashMessage(
        array $generation,
        int $submitted,
        int $failed,
        int $availity,
        int $sigmaDhs,
        int $eligibleCount
    ): string {
        if ($submitted === 0 && $eligibleCount === 0 && $generation['generated'] === 0) {
            return 'All eligible claims for this cycle are already submitted.';
        }

        $parts = [];

        if ($generation['generated'] > 0) {
            $parts[] = "Generated {$generation['generated']} new bill".($generation['generated'] === 1 ? '' : 's');
        }

        if ($generation['refreshed'] > 0) {
            $parts[] = "refreshed {$generation['refreshed']} existing";
        }

        if ($submitted > 0) {
            $parts[] = "submitted {$submitted} claim".($submitted === 1 ? '' : 's');
        }

        if ($availity > 0) {
            $parts[] = "{$availity} routed to Availity";
        }

        if ($sigmaDhs > 0) {
            $parts[] = "{$sigmaDhs} DHS invoice".($sigmaDhs === 1 ? '' : 's').' emailed to ASW (Sigma queued)';
        }

        if ($failed > 0) {
            $parts[] = "{$failed} failed — check integration credentials";
        }

        return ucfirst(implode('; ', $parts)).'.';
    }

    /**
     * @param  array{generated: int, refreshed: int, skipped: int}  $generation
     */
    protected function resolveFlashType(int $submitted, int $failed, int $eligibleCount, array $generation): string
    {
        if ($failed > 0 && $submitted === 0) {
            return 'warning';
        }

        if ($submitted > 0) {
            return $failed > 0 ? 'warning' : 'success';
        }

        if ($generation['generated'] > 0) {
            return 'success';
        }

        return $eligibleCount === 0 ? 'success' : 'warning';
    }
}

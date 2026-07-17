<?php

namespace App\Services\Billing;

use App\Models\BillingClaimAudit;
use App\Models\User;
use App\Services\BillingClaimAuditWorkflowService;

class BillingClaimChannelSubmissionService
{
    public const CHANNEL_AVAILITY = 'availity';

    public const CHANNEL_SIGMA_DHS = 'sigma_dhs';

    public const CHANNEL_UNKNOWN = 'unknown';

    public function __construct(
        protected BillingClaimSubmissionService $availitySubmissionService,
        protected DhsHomeHelpSubmissionService $dhsSubmissionService,
        protected BillingClaimAvailityStatusService $availityStatusService,
        protected BillingClaimAuditWorkflowService $workflowService,
    ) {}

    public function resolveChannel(BillingClaimAudit $audit): string
    {
        if ($this->availitySubmissionService->shouldSubmitViaAvaility($audit)) {
            return self::CHANNEL_AVAILITY;
        }

        if ($audit->isDhs() || $audit->usesSigmaPortal()) {
            return self::CHANNEL_SIGMA_DHS;
        }

        $channel = strtolower((string) $audit->submission_channel);

        if (str_contains($channel, 'sigma') || str_contains($channel, 'home help')) {
            return self::CHANNEL_SIGMA_DHS;
        }

        return self::CHANNEL_UNKNOWN;
    }

    /**
     * @return array{success: bool, channel: string, message: string, reference_id: ?string}
     */
    public function submit(BillingClaimAudit $audit, User $user): array
    {
        $channel = $this->resolveChannel($audit);

        $result = match ($channel) {
            self::CHANNEL_AVAILITY => $this->submitAvaility($audit, $user),
            self::CHANNEL_SIGMA_DHS => $this->submitDhs($audit, $user),
            default => [
                'success' => false,
                'channel' => self::CHANNEL_UNKNOWN,
                'message' => 'No submission channel is configured for this claim.',
                'reference_id' => null,
            ],
        };

        if ($result['success']) {
            $this->finalizeSuccessfulSubmission($audit, $user, $result);
        }

        return $result;
    }

    /**
     * @return array{success: bool, channel: string, message: string, reference_id: ?string}
     */
    protected function submitAvaility(BillingClaimAudit $audit, User $user): array
    {
        try {
            $apiResult = $this->availitySubmissionService->submit($audit);
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'channel' => self::CHANNEL_AVAILITY,
                'message' => 'Availity submission failed: '.$exception->getMessage(),
                'reference_id' => null,
            ];
        }

        if (! $apiResult['success']) {
            return [
                'success' => false,
                'channel' => self::CHANNEL_AVAILITY,
                'message' => 'Availity rejected the claim submission. Check Availity credentials and claim data.',
                'reference_id' => null,
            ];
        }

        $audit->refresh();

        if ($this->availityStatusService->canSync($audit)) {
            try {
                $this->availityStatusService->sync($audit, $user->id);
            } catch (\Throwable) {
                // Submission succeeded; status sync can be retried separately.
            }
        }

        $audit->billing_status = BillingClaimAudit::BILLING_SUBMITTED;
        $audit->billing_route = 'availity_837p';
        $audit->status_detail = '837P submitted to Availity';
        $audit->syncClaimStatusFromBillingStatus();

        return [
            'success' => true,
            'channel' => self::CHANNEL_AVAILITY,
            'message' => 'Claim submitted to Availity.',
            'reference_id' => $audit->availity_reference_id,
        ];
    }

    /**
     * @return array{success: bool, channel: string, message: string, reference_id: ?string}
     */
    protected function submitDhs(BillingClaimAudit $audit, User $user): array
    {
        $result = $this->dhsSubmissionService->submit($audit, $user);

        return [
            'success' => $result['success'],
            'channel' => self::CHANNEL_SIGMA_DHS,
            'message' => $result['message'],
            'reference_id' => $result['communication_id'] ? (string) $result['communication_id'] : null,
        ];
    }

    /**
     * @param  array{success: bool, channel: string, message: string, reference_id: ?string}  $result
     */
    protected function finalizeSuccessfulSubmission(BillingClaimAudit $audit, User $user, array $result): void
    {
        $audit->submitted_at = now();
        $audit->updated_by = $user->id;

        $events = $audit->lifecycle_events ?? [];
        $events[] = [
            'status' => 'completed',
            'title' => match ($result['channel']) {
                self::CHANNEL_AVAILITY => '837P submitted to Availity',
                self::CHANNEL_SIGMA_DHS => 'DHS invoice submitted (email + Sigma queue)',
                default => 'Claim submitted',
            },
            'date' => now()->format('M j, Y'),
            'detail' => $result['reference_id'] ? 'ref '.$result['reference_id'] : '',
        ];
        $audit->lifecycle_events = $events;

        $this->workflowService->appendActivity(
            $audit,
            'Claim submitted via '.$result['channel'],
            $user->id,
            $result['message']
        );

        $audit->save();
    }
}

<?php

namespace App\Services\Billing;

use App\Models\BillingClaimAudit;
use App\Services\AgencyIdentityService;
use App\Services\Availity\AvailityClient;
use Illuminate\Support\Facades\Log;

class BillingClaimSubmissionService
{
    public function __construct(
        protected AvailityClient $availityClient,
        protected AgencyIdentityService $agencyIdentity
    ) {}

    public function shouldSubmitViaAvaility(BillingClaimAudit $audit): bool
    {
        if ($audit->program_type !== BillingClaimAudit::PROGRAM_MICH) {
            return false;
        }

        $channel = strtolower((string) $audit->submission_channel);

        return str_contains($channel, 'availity');
    }

    public function buildAvailityPayload(BillingClaimAudit $audit): array
    {
        $audit->loadMissing(['client', 'employee']);
        $identity = $this->agencyIdentity->billingIdentity($audit->organization_id);

        // Billable hours come from payableHours (total_hours); verified_hours mirrors that gate.
        $hours = round((float) ($audit->total_hours ?? $audit->verified_hours ?? 0), 2);
        $units = (int) round($hours * 4);
        $billingCode = $audit->service_code ?: config('billing_claims_audit.standard_billing_code', 'T019');
        $hourlyRate = round((float) ($audit->hourly_rate ?? $audit->client?->billing_rate ?? 30), 2);
        $totalCharge = round((float) ($audit->total_amount ?? ($hours * $hourlyRate)), 2);

        return [
            'claimType' => '837P',
            'submissionChannel' => 'availity',
            'environment' => $this->availityClient->environmentLabel(),
            'referenceNumber' => $audit->claim_number ?: ('BCA-'.$audit->id),
            'servicePeriod' => [
                'startDate' => optional($audit->period_start)->toDateString(),
                'endDate' => optional($audit->period_end)->toDateString(),
            ],
            'billingProvider' => array_filter([
                'npi' => $identity['npi'],
                'taxId' => $identity['tax_id'],
                'medicaidProviderId' => $identity['medicaid_provider_id'],
                'organizationName' => $identity['legal_name'],
            ]),
            'patient' => array_filter([
                'firstName' => $audit->client?->first_name,
                'lastName' => $audit->client?->last_name,
                'memberId' => $audit->medicaid_id ?? $audit->client?->member_id,
                'medicaidId' => $audit->medicaid_id ?? $audit->client?->member_id,
            ]),
            'renderingProvider' => array_filter([
                'firstName' => $audit->employee?->first_name,
                'lastName' => $audit->employee?->last_name,
                'providerId' => $audit->employee?->champs_provider_id,
            ]),
            'serviceLines' => [[
                'procedureCode' => $billingCode,
                'units' => $units,
                'hours' => $hours,
                'chargeAmount' => $totalCharge,
                'serviceDateFrom' => optional($audit->period_start)->toDateString(),
                'serviceDateTo' => optional($audit->period_end)->toDateString(),
            ]],
            'totals' => [
                'hours' => $hours,
                'units' => $units,
                'chargeAmount' => $totalCharge,
            ],
        ];
    }

    /**
     * @return array{success: bool, claim_id: ?string, status: string}
     */
    public function submit(BillingClaimAudit $audit): array
    {
        if (! $this->shouldSubmitViaAvaility($audit)) {
            return ['success' => false, 'claim_id' => null, 'status' => 'skipped'];
        }

        $payload = $this->buildAvailityPayload($audit);
        $result = $this->availityClient->submitClaim($payload);

        Log::channel('availity')->info('Billing claim audit submitted to Availity', [
            'audit_id' => $audit->id,
            'success' => $result['success'],
            'claim_id' => $result['claim_id'] ?? null,
        ]);

        if ($result['success'] && ! empty($result['claim_id'])) {
            $audit->update([
                'availity_reference_id' => $result['claim_id'],
                'availity_status' => $result['status'],
                'availity_status_payload' => $result['raw'],
                'availity_status_checked_at' => now(),
                'payer_reference' => $result['claim_id'],
            ]);
        }

        return $result;
    }
}

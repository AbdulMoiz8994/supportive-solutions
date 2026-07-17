<?php

namespace App\Services\Availity;

use App\Models\BillingClaimAudit;
use App\Services\AgencyIdentityService;

class AvailityClaimStatusMapper
{
    public function __construct(
        protected AgencyIdentityService $agencyIdentity
    ) {}

    /**
     * Build Availity claim-statuses (276) inquiry form fields from a billing audit record.
     *
     * @return array<string, string>
     */
    public function fromBillingClaimAudit(BillingClaimAudit $audit): array
    {
        $audit->loadMissing(['client', 'employee']);
        $identity = $this->agencyIdentity->billingIdentity($audit->organization_id);

        $memberId = (string) ($audit->medicaid_id ?? $audit->plan_member_id ?? $audit->client?->member_id ?? '');
        $claimNumber = (string) ($audit->claim_number ?? $audit->invoice_number ?? ('BCA-'.$audit->id));
        $fromDate = optional($audit->period_start)->toDateString() ?? optional($audit->billing_period)->toDateString();
        $toDate = optional($audit->period_end)->toDateString() ?? $fromDate;

        $fields = [
            'payer.id' => config('services.availity.default_payer_id'),
            'submitter.lastName' => $identity['legal_name'] ?? config('app.name'),
            'submitter.firstName' => 'Billing',
            'submitter.id' => $identity['npi'] ?? config('services.availity.submitter_id') ?? 'SUBMITTER',
            'providers.lastName' => $audit->employee?->last_name ?? 'Provider',
            'providers.firstName' => $audit->employee?->first_name ?? 'Agency',
            'providers.npi' => $identity['npi'] ?? '',
            'subscriber.memberId' => $memberId,
            'subscriber.lastName' => $audit->client?->last_name ?? '',
            'subscriber.firstName' => $audit->client?->first_name ?? '',
            'patient.lastName' => $audit->client?->last_name ?? '',
            'patient.firstName' => $audit->client?->first_name ?? '',
            'patient.birthDate' => optional($audit->client?->dob)->toDateString() ?? '1990-01-01',
            'patient.genderCode' => strtoupper(substr((string) ($audit->client?->gender ?? 'U'), 0, 1)) ?: 'U',
            'patient.subscriberRelationshipCode' => (string) config('services.availity.patient_relationship_code', '18'),
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'claimNumber' => $claimNumber,
            'claimAmount' => number_format((float) ($audit->total_amount ?? 0), 2, '.', ''),
            'facilityTypeCode' => (string) config('services.availity.place_of_service_code', '12'),
            'frequencyTypeCode' => '1',
        ];

        return collect($fields)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }
}

<?php

namespace App\Services\Payroll;

use App\Models\PayRecord;
use App\Models\PayrollClaim;
use App\Services\AgencyIdentityService;
use App\Services\Availity\AvailityClient;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PayrollClaimService
{
    public function __construct(
        protected AvailityClient $availityClient,
        protected AgencyIdentityService $agencyIdentity
    ) {}

    public function shouldSubmitViaAvaility(PayRecord $record): bool
    {
        $record->loadMissing('client');

        if ($record->program_tag !== 'MICH') {
            return false;
        }

        return true;
    }

    public function findOrCreateDraft(PayRecord $record): PayrollClaim
    {
        $existing = PayrollClaim::withoutGlobalScopes()
            ->where('pay_record_id', $record->id)
            ->whereIn('status', [PayrollClaim::STATUS_DRAFT, PayrollClaim::STATUS_FAILED, PayrollClaim::STATUS_PENDING])
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $submitted = PayrollClaim::withoutGlobalScopes()
            ->where('pay_record_id', $record->id)
            ->whereIn('status', [PayrollClaim::STATUS_SUBMITTED, PayrollClaim::STATUS_APPROVED])
            ->exists();

        if ($submitted) {
            throw new InvalidArgumentException('A claim has already been submitted for this pay record.');
        }

        return PayrollClaim::withoutGlobalScopes()->create([
            'organization_id' => $record->organization_id,
            'pay_record_id'   => $record->id,
            'employee_id'     => $record->employee_id,
            'status'          => PayrollClaim::STATUS_DRAFT,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function validateForSubmission(PayRecord $record): void
    {
        $record->loadMissing(['employee', 'client']);

        $errors = [];

        if (! $record->employee) {
            $errors['employee'] = 'Caregiver is required for claim submission.';
        }

        if (! $record->client) {
            $errors['client'] = 'Client is required for claim submission.';
        }

        if (! $record->period_key) {
            $errors['period'] = 'Pay period is required for claim submission.';
        }

        if ($record->hours === null || (float) $record->hours <= 0) {
            $errors['hours'] = 'Verified hours must be greater than zero.';
        }

        if (! $this->shouldSubmitViaAvaility($record)) {
            $errors['program'] = 'Only MICH program pay records are routed through Availity.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function buildAvailityPayload(PayRecord $record): array
    {
        $this->validateForSubmission($record);

        $record->loadMissing(['employee', 'client']);
        $identity = $this->agencyIdentity->billingIdentity($record->organization_id);

        $period = Carbon::createFromFormat('Y-m', $record->period_key)->startOfMonth();
        $periodEnd = $period->copy()->endOfMonth();
        $hours = round((float) $record->hours, 2);
        $units = (int) round($hours * 4);
        $billingCode = config('billing_claims_audit.standard_billing_code', 'T019');
        $hourlyRate = round((float) ($record->client->billing_rate ?? 30.00), 2);
        $totalCharge = round($hours * $hourlyRate, 2);

        $payload = [
            'claimType'       => '837P',
            'submissionChannel' => 'availity',
            'environment'     => $this->availityClient->environmentLabel(),
            'referenceNumber' => 'PR-'.$record->id.'-'.$record->period_key,
            'servicePeriod'   => [
                'startDate' => $period->toDateString(),
                'endDate'   => $periodEnd->toDateString(),
            ],
            'patient' => $this->sanitize([
                'firstName'  => $record->client->first_name,
                'lastName'   => $record->client->last_name,
                'memberId'   => $record->client->member_id,
                'medicaidId' => $record->client->member_id,
            ]),
            'billingProvider' => $this->sanitize([
                'npi'                => $identity['npi'],
                'taxId'              => $identity['tax_id'],
                'medicaidProviderId' => $identity['medicaid_provider_id'],
                'organizationName'   => $identity['legal_name'],
            ]),
            'renderingProvider' => $this->sanitize([
                'firstName'  => $record->employee->first_name,
                'lastName'   => $record->employee->last_name,
                'providerId' => $record->employee->champs_provider_id,
            ]),
            'serviceLines' => [
                [
                    'procedureCode'   => $billingCode,
                    'procedureDescription' => 'Personal care services',
                    'units'           => $units,
                    'unitType'        => 'UN',
                    'hours'           => $hours,
                    'chargeAmount'    => $totalCharge,
                    'serviceDateFrom' => $period->toDateString(),
                    'serviceDateTo'   => $periodEnd->toDateString(),
                ],
            ],
            'totals' => [
                'hours'        => $hours,
                'units'        => $units,
                'chargeAmount' => $totalCharge,
            ],
            'payer' => $this->sanitize([
                'program' => $record->program_tag,
                'type'    => 'MCO',
            ]),
        ];

        Log::channel('availity')->info('Built Availity claim payload', [
            'pay_record_id' => $record->id,
            'payload'       => $payload,
        ]);

        return $payload;
    }

    /**
     * Queue Availity claim submission for a single pay record (async).
     */
    public function queueSubmission(PayRecord $record): void
    {
        if (! $this->shouldSubmitViaAvaility($record)) {
            return;
        }

        \App\Jobs\SubmitPayrollClaimJob::dispatch($record->id);
    }

    public function submitForPayRecord(PayRecord $record): PayrollClaim
    {
        if (! $this->shouldSubmitViaAvaility($record)) {
            throw new InvalidArgumentException('Pay record is not eligible for Availity claim submission.');
        }

        $claim = $this->findOrCreateDraft($record);
        $payload = $this->buildAvailityPayload($record);

        $claim->update([
            'status'          => PayrollClaim::STATUS_PENDING,
            'request_payload' => $payload,
            'error_message'   => null,
        ]);

        $result = $this->availityClient->submitClaim($payload);

        if ($result['success']) {
            $claim->update([
                'claim_reference_id' => $result['claim_id'],
                'status'             => $this->mapAvailityStatus($result['status']),
                'response_payload'   => $result['raw'],
                'submitted_at'       => now(),
                'error_message'      => null,
            ]);

            return $claim->fresh();
        }

        $errorMessage = Arr::get($result['raw'], 'message')
            ?? Arr::get($result['raw'], 'error')
            ?? 'Availity claim submission failed.';

        $claim->update([
            'status'           => PayrollClaim::STATUS_FAILED,
            'response_payload' => $result['raw'],
            'error_message'    => (string) $errorMessage,
        ]);

        throw new InvalidArgumentException((string) $errorMessage);
    }

    public function refreshClaimStatus(PayrollClaim $claim): PayrollClaim
    {
        if (! $claim->claim_reference_id) {
            throw new InvalidArgumentException('Claim reference ID is missing.');
        }

        $result = $this->availityClient->checkClaimStatus($claim->claim_reference_id);

        if (! $result['success']) {
            $claim->update([
                'status'           => PayrollClaim::STATUS_FAILED,
                'response_payload' => $result['raw'],
                'error_message'    => Arr::get($result['raw'], 'message', 'Status check failed.'),
            ]);

            return $claim->fresh();
        }

        $claim->update([
            'status'           => $this->mapAvailityStatus($result['status']),
            'response_payload' => $result['raw'],
            'error_message'    => null,
        ]);

        return $claim->fresh();
    }

    protected function mapAvailityStatus(string $status): string
    {
        return match ($status) {
            'approved' => PayrollClaim::STATUS_APPROVED,
            'rejected' => PayrollClaim::STATUS_REJECTED,
            'pending'  => PayrollClaim::STATUS_PENDING,
            'failed'   => PayrollClaim::STATUS_FAILED,
            default    => PayrollClaim::STATUS_SUBMITTED,
        };
    }

    protected function sanitize(array $data): array
    {
        return collect($data)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }
}

<?php

namespace App\Services\Availity;

class AvailityClaimPayloadMapper
{
    /**
     * Map our internal claim payload to Availity Professional Claims (837P) JSON.
     *
     * @param  array<string, mixed>  $internal
     * @return array<string, mixed>
     */
    public function toProfessionalClaim(array $internal): array
    {
        $billing = $internal['billingProvider'] ?? [];
        $patient = $internal['patient'] ?? [];
        $rendering = $internal['renderingProvider'] ?? [];
        $payer = $internal['payer'] ?? [];
        $servicePeriod = $internal['servicePeriod'] ?? [];
        $lines = $internal['serviceLines'] ?? [];
        $firstLine = $lines[0] ?? [];

        $fromDate = $firstLine['serviceDateFrom'] ?? $servicePeriod['startDate'] ?? null;
        $toDate = $firstLine['serviceDateTo'] ?? $servicePeriod['endDate'] ?? $fromDate;

        $memberId = $patient['medicaidId'] ?? $patient['memberId'] ?? null;

        $claim = [
            'requestTypeCode' => (string) config('services.availity.request_type_code'),
            'billingProvider' => array_filter([
                'npi' => $billing['npi'] ?? null,
                'ein' => isset($billing['taxId']) ? preg_replace('/\D/', '', (string) $billing['taxId']) : null,
                'payerAssignedProviderId' => $billing['medicaidProviderId'] ?? null,
                'organizationName' => $billing['organizationName'] ?? null,
            ]),
            'patient' => array_filter([
                'firstName' => $patient['firstName'] ?? null,
                'lastName' => $patient['lastName'] ?? null,
                'relationshipCode' => (string) config('services.availity.patient_relationship_code', '18'),
            ]),
            'payer' => [
                'id' => $payer['id'] ?? config('services.availity.default_payer_id'),
            ],
            'submitter' => array_filter([
                'id' => $billing['npi'] ?? config('services.availity.submitter_id'),
                'lastName' => $billing['organizationName'] ?? config('app.name'),
            ]),
            'subscriber' => array_filter([
                'memberId' => $memberId,
            ]),
            'claimInformation' => [
                'placeOfServiceCode' => (string) config('services.availity.place_of_service_code', '12'),
                'serviceLines' => collect($lines)->map(function (array $line) use ($fromDate, $toDate) {
                    return array_filter([
                        'procedureCode' => $line['procedureCode'] ?? null,
                        'quantity' => isset($line['units']) ? (string) $line['units'] : null,
                        'amount' => isset($line['chargeAmount']) ? number_format((float) $line['chargeAmount'], 2, '.', '') : null,
                        'fromDate' => $line['serviceDateFrom'] ?? $fromDate,
                        'toDate' => $line['serviceDateTo'] ?? $toDate,
                        'renderingProvider' => isset($line['renderingProvider']) ? $line['renderingProvider'] : null,
                    ]);
                })->values()->all(),
            ],
        ];

        if ($diagnosis = config('services.availity.default_diagnosis_code')) {
            $claim['claimInformation']['diagnoses'] = [[
                'qualifierCode' => 'ABK',
                'code' => $diagnosis,
            ]];
        }

        if ($rendering && ($rendering['firstName'] ?? $rendering['lastName'] ?? $rendering['providerId'] ?? null)) {
            $claim['renderingProvider'] = array_filter([
                'firstName' => $rendering['firstName'] ?? null,
                'lastName' => $rendering['lastName'] ?? null,
                'npi' => $rendering['providerId'] ?? null,
            ]);
        }

        if ($reference = $internal['referenceNumber'] ?? null) {
            $claim['customerId'] = (string) $reference;
        }

        return $this->filterEmpty($claim);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function filterEmpty(array $data): array
    {
        return collect($data)
            ->map(function ($value) {
                if (is_array($value)) {
                    $filtered = $this->filterEmpty($value);

                    return $filtered === [] ? null : $filtered;
                }

                return $value;
            })
            ->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])
            ->all();
    }
}

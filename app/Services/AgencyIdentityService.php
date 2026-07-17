<?php

namespace App\Services;

use App\Models\Organization;

class AgencyIdentityService
{
    protected ?Organization $cachedPrimary = null;

    public function primaryOrganization(): ?Organization
    {
        if ($this->cachedPrimary !== null) {
            return $this->cachedPrimary;
        }

        return $this->cachedPrimary = Organization::query()->orderBy('id')->first();
    }

    public function forOrganization(?int $organizationId): ?Organization
    {
        if ($organizationId === null) {
            return $this->primaryOrganization();
        }

        return Organization::find($organizationId);
    }

    /**
     * @return array{npi: ?string, tax_id: ?string, medicaid_provider_id: ?string, legal_name: ?string, address: array<string, ?string>}
     */
    public function billingIdentity(?int $organizationId = null): array
    {
        $org = $this->forOrganization($organizationId);

        return [
            'npi' => $org?->agency_npi,
            'tax_id' => $org?->tax_id_ein,
            'medicaid_provider_id' => $org?->medicaid_provider_id,
            'legal_name' => $org?->legal_business_name,
            'address' => [
                'street' => $org?->legal_address_street,
                'city' => $org?->legal_address_city,
                'state' => $org?->legal_address_state,
                'zip' => $org?->legal_address_zip,
            ],
        ];
    }

    public function updatePrimary(array $validated): Organization
    {
        $org = $this->primaryOrganization() ?? Organization::create([
            'name' => $validated['name'] ?? 'Primary Agency',
            'status' => 'Active',
        ]);

        $org->update($validated);
        $this->cachedPrimary = $org->fresh();

        return $this->cachedPrimary;
    }
}

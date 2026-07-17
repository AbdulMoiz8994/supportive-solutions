<?php

namespace Database\Seeders\Concerns;

use App\Models\Location;
use App\Models\Organization;
use App\Models\Status;

trait SeedsOrganizationContext
{
    protected function organization(): Organization
    {
        return Organization::firstOrCreate(
            ['name' => 'Supportive Solutions HomeCare LLC'],
            [
                'address' => '835 Mason St Suite C-116, Dearborn, MI 48124',
                'status' => 'Active',
                'agency_npi' => '1619784667',
                'tax_id_ein' => '331930284',
                'medicaid_provider_id' => '1619784667',
                'legal_business_name' => 'Supportive Solutions HomeCare LLC',
                'legal_address_street' => '835 Mason St Suite C-116',
                'legal_address_city' => 'Dearborn',
                'legal_address_state' => 'MI',
                'legal_address_zip' => '48124',
            ]
        );
    }

    protected function location(): Location
    {
        return Location::firstOrCreate(
            ['name' => 'Michigan Main'],
            ['state' => 'Michigan', 'is_active' => true]
        );
    }

    protected function statusId(string $entityType, string $name): ?int
    {
        return Status::where('entity_type', $entityType)
            ->where('name', $name)
            ->value('id');
    }
}

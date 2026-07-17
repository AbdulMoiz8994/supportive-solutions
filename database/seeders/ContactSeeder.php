<?php

namespace Database\Seeders;

use App\Models\Contact;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org = $this->organization();
        $location = $this->location();

        $contacts = [
            ['name' => 'Dr. Ahmed Hassan', 'type' => Contact::TYPE_PCP, 'phone' => '(313) 555-9001', 'fax' => '(313) 555-9901', 'email' => 'a.hassan@examplemed.com', 'clinic_name' => 'Regional Medical Center', 'provider_id' => 'NPI-1234567890'],
            ['name' => 'Dr. Sarah Malik', 'type' => Contact::TYPE_PCP, 'phone' => '(313) 555-9002', 'fax' => '(313) 555-9902', 'email' => 's.malik@examplehospital.com', 'clinic_name' => 'Community Hospital', 'provider_id' => 'NPI-0987654321'],
            ['name' => 'Mrs. Carol Johnson', 'type' => Contact::TYPE_CASE_COORDINATOR, 'phone' => '(313) 555-9003', 'fax' => '(313) 555-9903', 'email' => 'c.johnson@county.gov', 'clinic_name' => 'County Human Services', 'provider_id' => null],
            ['name' => 'Mr. Brian Thompson', 'type' => Contact::TYPE_CASE_COORDINATOR, 'phone' => '(313) 555-9004', 'fax' => '(313) 555-9904', 'email' => 'b.thompson@county.gov', 'clinic_name' => 'County Human Services - West', 'provider_id' => null],
            ['name' => 'Metro Pharmacy', 'type' => Contact::TYPE_PHARMACY, 'phone' => '(313) 555-9005', 'fax' => '(313) 555-9905', 'email' => 'rx@metropharmacy.com', 'clinic_name' => 'Metro Pharmacy - Main St', 'provider_id' => 'DEA-AB1234567'],
            ['name' => 'Dr. Nadia Khalil', 'type' => Contact::TYPE_PCP, 'phone' => '(313) 555-9006', 'fax' => '(313) 555-9906', 'email' => 'n.khalil@regionalhealth.com', 'clinic_name' => 'Regional Health System', 'provider_id' => 'NPI-1122334455'],
            ['name' => 'Regional Lab Services', 'type' => Contact::TYPE_VENDOR, 'phone' => '(313) 555-9007', 'fax' => '(313) 555-9907', 'email' => 'lab@regionalhealth.com', 'clinic_name' => 'Regional Diagnostic Lab', 'provider_id' => 'LAB-9988776655'],
            ['name' => 'Comfort Hospice', 'type' => Contact::TYPE_REFERRAL, 'phone' => '(313) 555-9008', 'fax' => '(313) 555-9908', 'email' => 'info@comforthospice.com', 'clinic_name' => 'Comfort Hospice Care', 'provider_id' => null],
        ];

        foreach ($contacts as $contact) {
            Contact::withoutGlobalScopes()->updateOrCreate(
                ['email' => $contact['email'], 'organization_id' => $org->id],
                array_merge($contact, [
                    'organization_id' => $org->id,
                    'location_id' => $location->id,
                    'is_active' => true,
                ])
            );
        }

        $this->command?->info('Directory contacts seeded.');
    }
}

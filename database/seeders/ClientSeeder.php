<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contact;
use App\Models\CoverageType;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org = $this->organization();
        $location = $this->location();

        $dhs = CoverageType::where('name', 'DHS Home Help')->first();
        $mich = CoverageType::where('name', 'MICH')->first();

        $clientsData = [
            ['first_name' => 'John', 'last_name' => 'Doe', 'dob' => '1950-05-15', 'phone' => '(313) 555-1001', 'email' => 'john.doe@mail.com', 'county' => 'Wayne', 'member_id' => 'MD-100001', 'status' => 'Active', 'billing_rate' => 18.50, 'address' => '123 Maple St, Detroit MI 48201', 'coverage_type_id' => $dhs?->id, 'mco_name' => null, 'asw_email' => 'denise.carter@mdhhs.michigan.gov', 'coordinator_email' => null],
            ['first_name' => 'Jane', 'last_name' => 'Smith', 'dob' => '1942-08-22', 'phone' => '(313) 555-1002', 'email' => 'jane.smith@mail.com', 'county' => 'Macomb', 'member_id' => 'MD-100002', 'status' => 'Active', 'billing_rate' => 17.00, 'address' => '456 Oak Ave, Warren MI 48089', 'coverage_type_id' => $mich?->id, 'mco_name' => 'Molina Healthcare of Michigan', 'asw_email' => null, 'coordinator_email' => 'c.johnson@county.gov'],
            ['first_name' => 'Robert', 'last_name' => 'Johnson', 'dob' => '1938-03-11', 'phone' => '(313) 555-1003', 'email' => 'robert.j@mail.com', 'county' => 'Oakland', 'member_id' => 'MD-100003', 'status' => 'Active', 'billing_rate' => 19.00, 'address' => '789 Pine Rd, Troy MI 48084', 'coverage_type_id' => $dhs?->id, 'mco_name' => null, 'asw_email' => 'marcus.reed@mdhhs.michigan.gov', 'coordinator_email' => null],
            ['first_name' => 'Mary', 'last_name' => 'Williams', 'dob' => '1955-11-30', 'phone' => '(313) 555-1004', 'email' => 'mary.w@mail.com', 'county' => 'Wayne', 'member_id' => 'MD-100004', 'status' => 'Active', 'billing_rate' => 18.00, 'address' => '321 Elm St, Detroit MI 48202', 'coverage_type_id' => $mich?->id, 'mco_name' => 'Aetna Better Health (via Availity)', 'asw_email' => null, 'coordinator_email' => 'b.thompson@county.gov'],
            ['first_name' => 'Barbara', 'last_name' => 'Brown', 'dob' => '1948-07-19', 'phone' => '(313) 555-1005', 'email' => 'barbara.b@mail.com', 'county' => 'Macomb', 'member_id' => 'MD-100005', 'status' => 'Pending', 'billing_rate' => 16.50, 'address' => '654 Cedar Ln, Sterling Heights MI', 'coverage_type_id' => $mich?->id, 'mco_name' => 'Meridian Health Plan', 'asw_email' => null, 'coordinator_email' => null],
            ['first_name' => 'James', 'last_name' => 'Davis', 'dob' => '1960-02-28', 'phone' => '(313) 555-1006', 'email' => 'james.d@mail.com', 'county' => 'Oakland', 'member_id' => 'MD-100006', 'status' => 'Active', 'billing_rate' => 20.00, 'address' => '987 Birch Blvd, Pontiac MI 48342', 'coverage_type_id' => $dhs?->id, 'mco_name' => null, 'asw_email' => 'sandra.ortiz@mdhhs.michigan.gov', 'coordinator_email' => null],
            ['first_name' => 'Patricia', 'last_name' => 'Miller', 'dob' => '1944-09-05', 'phone' => '(313) 555-1007', 'email' => 'patricia.m@mail.com', 'county' => 'Wayne', 'member_id' => 'MD-100007', 'status' => 'Discharged', 'billing_rate' => 17.50, 'address' => '159 Walnut Way, Dearborn MI 48124', 'coverage_type_id' => $mich?->id, 'mco_name' => 'UnitedHealthcare Community Plan', 'asw_email' => null, 'coordinator_email' => null],
            ['first_name' => 'Linda', 'last_name' => 'Wilson', 'dob' => '1953-04-14', 'phone' => '(313) 555-1008', 'email' => 'linda.w@mail.com', 'county' => 'Macomb', 'member_id' => 'MD-100008', 'status' => 'Active', 'billing_rate' => 18.75, 'address' => '753 Ash Court, Clinton Township MI', 'coverage_type_id' => $mich?->id, 'mco_name' => 'Blue Cross Complete', 'asw_email' => null, 'coordinator_email' => 'c.johnson@county.gov'],
        ];

        foreach ($clientsData as $data) {
            $aswEmail = $data['asw_email'];
            $coordinatorEmail = $data['coordinator_email'];
            unset($data['asw_email'], $data['coordinator_email']);

            $client = Client::withoutGlobalScopes()->updateOrCreate(
                ['member_id' => $data['member_id'], 'organization_id' => $org->id],
                array_merge($data, [
                    'organization_id' => $org->id,
                    'location_id' => $location->id,
                    'status_id' => $this->statusId('Client', $data['status']),
                ])
            );

            $this->linkDirectoryContact($client, $aswEmail, 'ASW · Adult Services Worker');
            $this->linkDirectoryContact($client, $coordinatorEmail, 'Case Coordinator');
        }

        $this->command?->info('Clients seeded with program, MCO, and directory links.');
    }

    protected function linkDirectoryContact(Client $client, ?string $email, string $role): void
    {
        if (! $email) {
            return;
        }

        $contact = Contact::withoutGlobalScopes()
            ->where('organization_id', $client->organization_id)
            ->where('email', $email)
            ->first();

        if (! $contact) {
            return;
        }

        $client->contacts()->syncWithoutDetaching([
            $contact->id => ['role' => $role],
        ]);

        // Keep a single pivot row per role type.
        $matches = str_contains(strtolower($role), 'asw')
            ? fn ($c) => str_contains(strtolower($c->pivot->role ?? ''), 'asw')
            : fn ($c) => str_contains(strtolower($c->pivot->role ?? ''), 'coordinator') || $c->type === Contact::TYPE_CASE_COORDINATOR;

        $client->load('contacts');
        $keepId = $contact->id;

        foreach ($client->contacts as $linked) {
            if ((int) $linked->id === $keepId) {
                continue;
            }

            if ($matches($linked)) {
                $client->contacts()->detach($linked->id);
            }
        }
    }
}

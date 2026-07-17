<?php

namespace Database\Seeders;

use App\Models\Intake;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

class IntakeSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org = $this->organization();
        $location = $this->location();

        $intakesData = [
            ['first_name' => 'Alice', 'last_name' => 'Green', 'dob' => '1958-03-21', 'phone' => '(313) 555-3001', 'email' => 'alice.g@gmail.com', 'source' => 'Referral', 'pipeline_status' => 'New Lead', 'status' => 'New', 'notes' => 'Referred by Dr. Hassan. Needs 28 hrs/week care.'],
            ['first_name' => 'Thomas', 'last_name' => 'White', 'dob' => '1945-12-09', 'phone' => '(313) 555-3002', 'email' => 'thomas.w@gmail.com', 'source' => 'Walk-In', 'pipeline_status' => 'New Lead', 'status' => 'New', 'notes' => 'Visited office directly. Son is caretaker POA.'],
            ['first_name' => 'Gloria', 'last_name' => 'Harris', 'dob' => '1962-06-15', 'phone' => '(313) 555-3003', 'email' => 'gloria.h@gmail.com', 'source' => 'Hospital Discharge', 'pipeline_status' => 'Contacted', 'status' => 'Contacted', 'notes' => 'Discharged from Beaumont on 2026-04-01. Needs immediate placement.'],
            ['first_name' => 'Raymond', 'last_name' => 'Clark', 'dob' => '1939-09-28', 'phone' => '(313) 555-3004', 'email' => 'raymond.c@gmail.com', 'source' => 'Medicaid Office', 'pipeline_status' => 'Contacted', 'status' => 'Contacted', 'notes' => 'Medicaid approved. Coordinator is Mrs. Johnson at DHS.'],
            ['first_name' => 'Dorothy', 'last_name' => 'Lewis', 'dob' => '1951-01-17', 'phone' => '(313) 555-3005', 'email' => 'dorothy.l@gmail.com', 'source' => 'Facebook Ad', 'pipeline_status' => 'Intake Scheduled', 'status' => 'Pending', 'notes' => 'Called from Facebook ad. Needs evaluation scheduled.'],
            ['first_name' => 'Howard', 'last_name' => 'Walker', 'dob' => '1947-08-04', 'phone' => '(313) 555-3006', 'email' => 'howard.w@gmail.com', 'source' => 'Google Search', 'pipeline_status' => 'New Lead', 'status' => 'New', 'notes' => 'Found us via Google. 79 years old, diabetic, needs daily care.'],
        ];

        foreach ($intakesData as $data) {
            $pipelineStatus = $data['pipeline_status'];
            unset($data['pipeline_status']);

            Intake::withoutGlobalScopes()->updateOrCreate(
                ['email' => $data['email'], 'organization_id' => $org->id],
                array_merge($data, [
                    'organization_id' => $org->id,
                    'location_id' => $location->id,
                    'status_id' => $this->statusId('Intake', $pipelineStatus),
                ])
            );
        }

        $this->command?->info('Intakes seeded.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org = $this->organization();
        $location = $this->location();
        $caregiverUser = User::where('email', 'caregiver@beydountech.com')->first();
        $caregiver2User = User::where('email', 'caregiver2@beydountech.com')->first();

        $employeesData = [
            ['first_name' => 'Sarah', 'last_name' => 'Connor', 'email' => 'sarah.c@agency.com', 'phone' => '(313) 555-2001', 'position' => 'Caregiver', 'status' => 'Active', 'hire_date' => '2023-01-10', 'address' => '111 Park St, Detroit MI', 'user_id' => $caregiverUser?->id],
            ['first_name' => 'Michael', 'last_name' => 'Rodriguez', 'email' => 'mike.r@agency.com', 'phone' => '(313) 555-2002', 'position' => 'Caregiver', 'status' => 'Active', 'hire_date' => '2021-11-05', 'address' => '222 Lake Dr, Detroit MI', 'user_id' => $caregiver2User?->id],
            ['first_name' => 'Angela', 'last_name' => 'Thompson', 'email' => 'angela.t@agency.com', 'phone' => '(313) 555-2003', 'position' => 'Nurse', 'status' => 'Active', 'hire_date' => '2022-06-15', 'address' => '333 River Rd, Detroit MI', 'user_id' => null],
            ['first_name' => 'David', 'last_name' => 'Martinez', 'email' => 'david.m@agency.com', 'phone' => '(313) 555-2004', 'position' => 'Caregiver', 'status' => 'Active', 'hire_date' => '2020-03-20', 'address' => '444 Hill Ave, Detroit MI', 'user_id' => null],
            ['first_name' => 'Lisa', 'last_name' => 'Anderson', 'email' => 'lisa.a@agency.com', 'phone' => '(313) 555-2005', 'position' => 'Office Staff', 'status' => 'Active', 'hire_date' => '2019-08-01', 'address' => '555 Forest Blvd, Detroit MI', 'user_id' => null],
            ['first_name' => 'Kevin', 'last_name' => 'Jackson', 'email' => 'kevin.j@agency.com', 'phone' => '(313) 555-2006', 'position' => 'Caregiver', 'status' => 'On Leave', 'hire_date' => '2023-05-12', 'address' => '666 Ridge Way, Detroit MI', 'user_id' => null],
        ];

        foreach ($employeesData as $data) {
            $statusName = $data['status'] === 'On Leave' ? 'On Leave' : 'Active';

            Employee::withoutGlobalScopes()->updateOrCreate(
                ['email' => $data['email'], 'organization_id' => $org->id],
                array_merge($data, [
                    'organization_id' => $org->id,
                    'location_id' => $location->id,
                    'status_id' => $this->statusId('Employee', $statusName),
                ])
            );
        }

        $this->command?->info('Employees seeded.');
    }
}

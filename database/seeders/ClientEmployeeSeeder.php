<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Employee;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientEmployeeSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org = $this->organization();

        $assignments = [
            ['MD-100001', 'sarah.c@agency.com'],
            ['MD-100002', 'sarah.c@agency.com'],
            ['MD-100003', 'mike.r@agency.com'],
            ['MD-100004', 'david.m@agency.com'],
            ['MD-100006', 'angela.t@agency.com'],
            ['MD-100008', 'david.m@agency.com'],
        ];

        foreach ($assignments as [$memberId, $employeeEmail]) {
            $client = Client::withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->where('member_id', $memberId)
                ->first();

            $employee = Employee::withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->where('email', $employeeEmail)
                ->first();

            if (! $client || ! $employee) {
                continue;
            }

            DB::table('client_employee')->updateOrInsert(
                ['client_id' => $client->id, 'employee_id' => $employee->id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->command?->info('Client-employee assignments seeded.');
    }
}

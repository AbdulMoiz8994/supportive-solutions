<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Schedule;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org = $this->organization();
        $location = $this->location();

        $client = fn (string $memberId) => Client::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('member_id', $memberId)
            ->first();

        $employee = fn (string $email) => Employee::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('email', $email)
            ->first();

        $scheduleRows = [
            ['member_id' => 'MD-100001', 'employee_email' => 'sarah.c@agency.com', 'date' => '2026-04-01', 'start_time' => '08:00:00', 'end_time' => '12:00:00', 'actual_clock_in' => '2026-04-01 08:03:00', 'actual_clock_out' => '2026-04-01 12:05:00', 'total_hours' => 4.03, 'status' => 'Completed', 'evv_status' => true, 'clock_in_latitude' => 42.3314, 'clock_in_longitude' => -83.0458, 'clock_out_latitude' => 42.3314, 'clock_out_longitude' => -83.0458],
            ['member_id' => 'MD-100002', 'employee_email' => 'sarah.c@agency.com', 'date' => '2026-04-02', 'start_time' => '09:00:00', 'end_time' => '13:00:00', 'actual_clock_in' => '2026-04-02 09:01:00', 'actual_clock_out' => '2026-04-02 13:02:00', 'total_hours' => 4.02, 'status' => 'Completed', 'evv_status' => true, 'clock_in_latitude' => 42.4894, 'clock_in_longitude' => -83.0225, 'clock_out_latitude' => 42.4894, 'clock_out_longitude' => -83.0225],
            ['member_id' => 'MD-100003', 'employee_email' => 'mike.r@agency.com', 'date' => '2026-04-03', 'start_time' => '10:00:00', 'end_time' => '14:00:00', 'actual_clock_in' => '2026-04-03 10:00:00', 'actual_clock_out' => '2026-04-03 14:00:00', 'total_hours' => 4.00, 'status' => 'Completed', 'evv_status' => true, 'clock_in_latitude' => 42.5803, 'clock_in_longitude' => -83.1468, 'clock_out_latitude' => 42.5803, 'clock_out_longitude' => -83.1468],
            ['member_id' => 'MD-100004', 'employee_email' => 'david.m@agency.com', 'date' => '2026-04-04', 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'actual_clock_in' => '2026-04-04 08:05:00', 'actual_clock_out' => '2026-04-04 10:07:00', 'total_hours' => 2.03, 'status' => 'Completed', 'evv_status' => true, 'clock_in_latitude' => 42.3319, 'clock_in_longitude' => -83.0467, 'clock_out_latitude' => 42.3319, 'clock_out_longitude' => -83.0467],
            ['member_id' => 'MD-100006', 'employee_email' => 'angela.t@agency.com', 'date' => '2026-04-05', 'start_time' => '14:00:00', 'end_time' => '18:00:00', 'actual_clock_in' => '2026-04-05 14:02:00', 'actual_clock_out' => '2026-04-05 18:01:00', 'total_hours' => 3.98, 'status' => 'Completed', 'evv_status' => true, 'clock_in_latitude' => 42.6389, 'clock_in_longitude' => -83.2910, 'clock_out_latitude' => 42.6389, 'clock_out_longitude' => -83.2910],
            ['member_id' => 'MD-100008', 'employee_email' => 'david.m@agency.com', 'date' => '2026-04-06', 'start_time' => '09:00:00', 'end_time' => '13:00:00', 'actual_clock_in' => null, 'actual_clock_out' => null, 'total_hours' => null, 'status' => 'Missed', 'evv_status' => false, 'clock_in_latitude' => null, 'clock_in_longitude' => null, 'clock_out_latitude' => null, 'clock_out_longitude' => null],
            ['member_id' => 'MD-100001', 'employee_email' => 'sarah.c@agency.com', 'date' => '2026-06-16', 'start_time' => '08:00:00', 'end_time' => '12:00:00', 'actual_clock_in' => null, 'actual_clock_out' => null, 'total_hours' => null, 'status' => 'Scheduled', 'evv_status' => false, 'clock_in_latitude' => null, 'clock_in_longitude' => null, 'clock_out_latitude' => null, 'clock_out_longitude' => null],
            ['member_id' => 'MD-100002', 'employee_email' => 'sarah.c@agency.com', 'date' => '2026-06-16', 'start_time' => '13:00:00', 'end_time' => '17:00:00', 'actual_clock_in' => null, 'actual_clock_out' => null, 'total_hours' => null, 'status' => 'Scheduled', 'evv_status' => false, 'clock_in_latitude' => null, 'clock_in_longitude' => null, 'clock_out_latitude' => null, 'clock_out_longitude' => null],
            ['member_id' => 'MD-100003', 'employee_email' => 'mike.r@agency.com', 'date' => '2026-06-17', 'start_time' => '10:00:00', 'end_time' => '14:00:00', 'actual_clock_in' => null, 'actual_clock_out' => null, 'total_hours' => null, 'status' => 'Scheduled', 'evv_status' => false, 'clock_in_latitude' => null, 'clock_in_longitude' => null, 'clock_out_latitude' => null, 'clock_out_longitude' => null],
            ['member_id' => 'MD-100004', 'employee_email' => 'david.m@agency.com', 'date' => '2026-06-17', 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'actual_clock_in' => null, 'actual_clock_out' => null, 'total_hours' => null, 'status' => 'Scheduled', 'evv_status' => false, 'clock_in_latitude' => null, 'clock_in_longitude' => null, 'clock_out_latitude' => null, 'clock_out_longitude' => null],
            ['member_id' => 'MD-100006', 'employee_email' => 'angela.t@agency.com', 'date' => '2026-06-18', 'start_time' => '14:00:00', 'end_time' => '18:00:00', 'actual_clock_in' => null, 'actual_clock_out' => null, 'total_hours' => null, 'status' => 'Scheduled', 'evv_status' => false, 'clock_in_latitude' => null, 'clock_in_longitude' => null, 'clock_out_latitude' => null, 'clock_out_longitude' => null],
        ];

        foreach ($scheduleRows as $row) {
            $clientModel = $client($row['member_id']);
            $employeeModel = $employee($row['employee_email']);

            if (! $clientModel || ! $employeeModel) {
                continue;
            }

            Schedule::withoutGlobalScopes()->updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'client_id' => $clientModel->id,
                    'employee_id' => $employeeModel->id,
                    'date' => $row['date'],
                    'start_time' => $row['start_time'],
                ],
                [
                    'end_time' => $row['end_time'],
                    'actual_clock_in' => $row['actual_clock_in'],
                    'actual_clock_out' => $row['actual_clock_out'],
                    'total_hours' => $row['total_hours'],
                    'status' => $row['status'],
                    'evv_status' => $row['evv_status'],
                    'clock_in_latitude' => $row['clock_in_latitude'],
                    'clock_in_longitude' => $row['clock_in_longitude'],
                    'clock_out_latitude' => $row['clock_out_latitude'],
                    'clock_out_longitude' => $row['clock_out_longitude'],
                    'location_id' => $location->id,
                    'visit_notes' => ['note' => 'Routine visit completed as scheduled.'],
                ]
            );
        }

        $this->command?->info('Schedules seeded.');
    }
}

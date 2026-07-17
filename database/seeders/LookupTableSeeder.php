<?php

namespace Database\Seeders;

use App\Models\CoverageType;
use App\Models\Status;
use Illuminate\Database\Seeder;

class LookupTableSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Pending',    'entity_type' => 'Client', 'color' => 'gray'],
            ['name' => 'Active',     'entity_type' => 'Client', 'color' => 'green'],
            ['name' => 'On Hold',    'entity_type' => 'Client', 'color' => 'amber'],
            ['name' => 'Recovery',   'entity_type' => 'Client', 'color' => 'blue'],
            ['name' => 'Discharged', 'entity_type' => 'Client', 'color' => 'red'],
            ['name' => 'Deceased',   'entity_type' => 'Client', 'color' => 'gray'],
            ['name' => 'Denied',     'entity_type' => 'Client', 'color' => 'red'],
            ['name' => 'Intake Completed', 'entity_type' => 'Client', 'color' => 'blue'],

            ['name' => 'Active', 'entity_type' => 'Employee', 'color' => 'green'],
            ['name' => 'Probation', 'entity_type' => 'Employee', 'color' => 'yellow'],
            ['name' => 'On Leave', 'entity_type' => 'Employee', 'color' => 'gray'],
            ['name' => 'Terminated', 'entity_type' => 'Employee', 'color' => 'red'],

            ['name' => 'New Lead', 'entity_type' => 'Intake', 'color' => 'blue'],
            ['name' => 'Contacted', 'entity_type' => 'Intake', 'color' => 'yellow'],
            ['name' => 'Intake Scheduled', 'entity_type' => 'Intake', 'color' => 'orange'],
            ['name' => 'Intake Completed', 'entity_type' => 'Intake', 'color' => 'indigo'],
            ['name' => 'Converted', 'entity_type' => 'Intake', 'color' => 'green'],
            ['name' => 'Not Interested', 'entity_type' => 'Intake', 'color' => 'gray'],
            ['name' => 'Non-Converted', 'entity_type' => 'Intake', 'color' => 'red'],

            ['name' => 'Scheduled', 'entity_type' => 'Schedule', 'color' => 'blue'],
            ['name' => 'Clocked In', 'entity_type' => 'Schedule', 'color' => 'orange'],
            ['name' => 'Completed', 'entity_type' => 'Schedule', 'color' => 'indigo'],
            ['name' => 'Verified', 'entity_type' => 'Schedule', 'color' => 'green'],
            ['name' => 'Cancelled', 'entity_type' => 'Schedule', 'color' => 'red'],
        ];

        foreach ($statuses as $status) {
            Status::updateOrCreate(
                ['name' => $status['name'], 'entity_type' => $status['entity_type']],
                $status
            );
        }

        $coverageTypes = [
            ['name' => 'DHS Home Help',  'plan_name' => 'DHS Home Help Program',              'description' => 'Michigan DHS Home Help (state-funded direct care)'],
            ['name' => 'MICH',           'plan_name' => 'MICH Program',                       'description' => 'MI Choice Waiver / Medicaid managed home care'],
            ['name' => 'ICO',            'plan_name' => 'Integrated Care Organization',       'description' => 'Medicare-Medicaid integrated care plan'],
            ['name' => 'DAAA',           'plan_name' => 'Detroit AAA / Area Agency on Aging', 'description' => 'Area Agency on Aging — Older Michiganians Act'],
            ['name' => 'Private Pay',    'plan_name' => 'Self-Pay',                           'description' => 'Out-of-pocket / private payment'],
        ];

        foreach ($coverageTypes as $type) {
            CoverageType::updateOrCreate(['name' => $type['name']], $type);
        }

        $this->command?->info('Lookup tables seeded.');
    }
}

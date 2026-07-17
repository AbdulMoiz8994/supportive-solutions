<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ComplianceForm;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\PayRecord;
use App\Services\PayrollHoursResolver;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PayrollSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->first();

        if (! $org) {
            return;
        }

        $resolver = app(PayrollHoursResolver::class);

        PayRecord::withoutGlobalScopes()->orderBy('id')->each(function (PayRecord $record) use ($resolver) {
            if (! $record->period_key) {
                $record->period_key = $resolver->periodKeyFromLabel($record->period);
            }

            if (! $record->caregiver_type && $record->employee) {
                $type = strtolower((string) $record->employee->caregiver_type);
                $record->caregiver_type = str_contains($type, 'family') || $record->employee->relationship_to_client
                    ? PayRecord::CAREGIVER_FAMILY
                    : PayRecord::CAREGIVER_AGENCY;
            }

            $record->saveQuietly();
        });

        $this->seedDemoCycle($org->id);
    }

    protected function seedDemoCycle(int $orgId): void
    {
        $periodKey = '2026-05';
        $periodLabel = 'May 2026';

        $existing = PayRecord::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('period_key', $periodKey)
            ->count();

        if ($existing >= 10) {
            return;
        }

        $clients = Client::withoutGlobalScopes()->where('organization_id', $orgId)->take(3)->get();
        $clientA = $clients->first();
        $clientB = $clients->skip(1)->first() ?? $clientA;
        $clientC = $clients->skip(2)->first() ?? $clientA;

        if (! $clientA) {
            return;
        }

        $statuses = [
            ['status' => PayRecord::STATUS_READY, 'hours' => 108, 'hold' => null],
            ['status' => PayRecord::STATUS_IN_GRACE, 'hours' => 120, 'hold' => null],
            ['status' => PayRecord::STATUS_IN_GRACE, 'hours' => 96, 'hold' => null],
            ['status' => PayRecord::STATUS_IN_GRACE, 'hours' => 88, 'hold' => null],
            ['status' => PayRecord::STATUS_IN_GRACE, 'hours' => 100, 'hold' => null],
            ['status' => PayRecord::STATUS_LATE_ROLLED, 'hours' => 72, 'hold' => null],
            ['status' => PayRecord::STATUS_LATE_ROLLED, 'hours' => 80, 'hold' => null],
            ['status' => PayRecord::STATUS_HELD, 'hours' => 90, 'hold' => 'DIG re-check required'],
            ['status' => PayRecord::STATUS_HELD, 'hours' => 85, 'hold' => 'Eligibility re-check'],
        ];

        foreach ($statuses as $i => $cfg) {
            $employee = Employee::withoutGlobalScopes()->create([
                'organization_id'  => $orgId,
                'first_name'       => 'Demo',
                'last_name'        => 'Caregiver '.($i + 1),
                'status'           => 'Active',
                'hourly_wage'      => 15.00,
                'caregiver_type'   => $i % 3 === 0 ? 'Family caregiver' : 'Agency-sourced',
                'live_in'          => $i % 4 === 0,
                'payroll_system'   => 'AccountantsWorld',
                'direct_deposit_last4' => str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT),
            ]);

            $client = [$clientA, $clientB, $clientC][$i % 3];
            $program = $i % 2 === 0 ? 'MICH' : 'DHS';

            $submittedAt = match ($cfg['status']) {
                PayRecord::STATUS_IN_GRACE => now()->subDays(3),
                PayRecord::STATUS_LATE_ROLLED => Carbon::create(2026, 6, 8),
                default => now()->subDays(15),
            };

            $form = ComplianceForm::withoutGlobalScopes()->create([
                'organization_id'   => $orgId,
                'employee_id'       => $employee->id,
                'client_id'         => $client->id,
                'period'            => $periodKey,
                'period_label'      => $periodLabel,
                'status'            => ComplianceForm::STATUS_VERIFIED,
                'delivered_hours'   => $cfg['hours'],
                'authorized_hours'  => 120,
                'submitted_at'      => $submittedAt,
                'service_start'     => '2026-05-01',
                'service_end'       => '2026-05-31',
            ]);

            PayRecord::withoutGlobalScopes()->create([
                'organization_id'      => $orgId,
                'employee_id'          => $employee->id,
                'client_id'            => $client->id,
                'compliance_form_id'   => $form->id,
                'period'               => $periodLabel,
                'period_key'           => $periodKey,
                'hours'                => $cfg['hours'],
                'rate'                 => 15.00,
                'gross'                => round($cfg['hours'] * 15, 2),
                'status'               => $cfg['status'],
                'hours_source'         => $employee->live_in ? 'from compliance form' : 'EVV clocked',
                'grace_end_date'       => $cfg['status'] === PayRecord::STATUS_IN_GRACE
                    ? $submittedAt->copy()->addDays(10)
                    : null,
                'hold_reason'          => $cfg['hold'],
                'caregiver_type'       => str_contains(strtolower($employee->caregiver_type), 'family')
                    ? PayRecord::CAREGIVER_FAMILY
                    : PayRecord::CAREGIVER_AGENCY,
                'program_tag'          => $program,
                'verified_at'          => $submittedAt,
            ]);
        }
    }
}

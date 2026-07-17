<?php

namespace Database\Seeders;

use App\Models\BillingClaimAudit;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Faker\Factory;

class BillingClaimAuditSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->first();

        if (! $org) {
            return;
        }

        $clients = Client::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->limit(20)
            ->get();

        if ($clients->isEmpty()) {
            return;
        }

        $caregivers = Employee::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('position', 'Caregiver')
            ->limit(10)
            ->get();

        $periods = [
            Carbon::create(2024, 5, 1),
            Carbon::create(2024, 4, 1),
            Carbon::create(2024, 3, 1),
        ];

        foreach ($periods as $period) {
            $this->seedPeriod($org->id, $clients, $caregivers, $period);
        }

        $this->seedShowcaseRecords($org->id, $clients, $caregivers);
    }

    private function seedShowcaseRecords(int $orgId, $clients, $caregivers): void
    {
        $maria = $clients->first();
        $yousef = $caregivers->first();

        BillingClaimAudit::updateOrCreate(
            ['organization_id' => $orgId, 'claim_number' => '837P-2026-05-0427'],
            $this->michClaimData($orgId, $maria?->id, $yousef?->id, Carbon::create(2026, 5, 1), [
                'client_name' => 'Maria Hassan',
                'caregiver_name' => 'Yousef Hassan',
                'health_plan' => 'Molina Healthcare',
                'hours' => 108.0,
                'rate' => 30.00,
                'units' => 432,
                'status' => BillingClaimAudit::STATUS_PAID,
                'status_detail' => 'Paid - EOB posted',
                'paid_amount' => 3240.00,
            ])
        );

        $khalil = $clients->skip(1)->first() ?? $maria;
        $layla = $caregivers->skip(1)->first() ?? $yousef;

        BillingClaimAudit::updateOrCreate(
            ['organization_id' => $orgId, 'claim_number' => 'HH-2026-05-0188'],
            $this->dhsClaimData($orgId, $khalil?->id, $layla?->id, Carbon::create(2026, 5, 1), [
                'client_name' => 'Khalil Ahmed',
                'caregiver_name' => 'Layla Ahmed',
                'hours' => 92.0,
                'rate' => 27.00,
                'status' => BillingClaimAudit::STATUS_AWAITING_PAYMENT,
                'status_detail' => 'Awaiting Sigma posting',
            ])
        );
    }

    private function seedPeriod(int $orgId, $clients, $caregivers, Carbon $period): void
    {
        $faker = Factory::create();

        $statusDistribution = [
            BillingClaimAudit::STATUS_PAID => 83,
            BillingClaimAudit::STATUS_AWAITING_PAYMENT => 48,
            BillingClaimAudit::STATUS_SUBMITTED => 131,
            BillingClaimAudit::STATUS_ON_HOLD => 5,
            BillingClaimAudit::STATUS_REJECTED => 2,
        ];

        $total = 141;
        $created = 0;
        $statusPool = [];

        foreach ($statusDistribution as $status => $count) {
            for ($i = 0; $i < $count && $created < $total; $i++) {
                $statusPool[] = $status;
                $created++;
            }
        }

        shuffle($statusPool);

        for ($i = 0; $i < $total; $i++) {
            $client = $clients->random();
            $caregiver = $caregivers->isNotEmpty() ? $caregivers->random() : null;
            $program = $faker->randomElement([BillingClaimAudit::PROGRAM_MICH, BillingClaimAudit::PROGRAM_DHS]);
            $status = $statusPool[$i] ?? BillingClaimAudit::STATUS_SUBMITTED;
            $hours = $faker->randomFloat(1, 60, 120);
            $rate = $program === BillingClaimAudit::PROGRAM_MICH ? 30.00 : 27.00;
            $amount = round($hours * $rate, 2);
            $periodEnd = $period->copy()->endOfMonth();
            $submittedAt = $periodEnd->copy()->subDays($faker->numberBetween(0, 5));

            $prefix = $program === BillingClaimAudit::PROGRAM_MICH ? '837P' : 'HH';
            $claimNumber = sprintf('%s-%s-%04d', $prefix, $period->format('Y-m'), $i + 1);

            $data = $program === BillingClaimAudit::PROGRAM_MICH
                ? $this->michClaimData($orgId, $client->id, $caregiver?->id, $period, [
                    'hours' => $hours,
                    'rate' => $rate,
                    'status' => $status,
                    'submitted_at' => $submittedAt,
                ])
                : $this->dhsClaimData($orgId, $client->id, $caregiver?->id, $period, [
                    'hours' => $hours,
                    'rate' => $rate,
                    'status' => $status,
                    'submitted_at' => $submittedAt,
                ]);

            $data['claim_number'] = $claimNumber;
            $data['total_amount'] = $amount;
            $data['paid_amount'] = $status === BillingClaimAudit::STATUS_PAID ? $amount : null;
            $data['paid_at'] = $status === BillingClaimAudit::STATUS_PAID ? $submittedAt->copy()->addDays(14) : null;

            if ($status === BillingClaimAudit::STATUS_ON_HOLD) {
                $data['hold_reason'] = 'CP-01 prior balance';
                $data['status_detail'] = 'On hold (CP-01)';
            }

            if ($status === BillingClaimAudit::STATUS_REJECTED) {
                $data['status_detail'] = 'Rejected - re-submit needed';
                $data['rejection_reason'] = 'Member ID mismatch';
            }

            if ($status === BillingClaimAudit::STATUS_AWAITING_PAYMENT) {
                $data['status_detail'] = $faker->randomElement([
                    'EOB pending', 'Awaiting Sigma posting', 'awaiting EOB', 'Sigma never posted',
                ]);
            }

            BillingClaimAudit::updateOrCreate(
                ['organization_id' => $orgId, 'claim_number' => $claimNumber],
                $data
            );
        }
    }

    private function michClaimData(int $orgId, ?int $clientId, ?int $employeeId, Carbon $period, array $overrides = []): array
    {
        $hours = $overrides['hours'] ?? 108.0;
        $rate = $overrides['rate'] ?? 30.00;
        $status = $overrides['status'] ?? BillingClaimAudit::STATUS_PAID;
        $periodEnd = $period->copy()->endOfMonth();
        $submittedAt = $overrides['submitted_at'] ?? $periodEnd->copy();

        return [
            'organization_id' => $orgId,
            'client_id' => $clientId,
            'employee_id' => $employeeId,
            'program_type' => BillingClaimAudit::PROGRAM_MICH,
            'billing_period' => $period->toDateString(),
            'period_start' => $period->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'total_hours' => $hours,
            'service_code' => 'T019',
            'service_description' => 'Personal care services',
            'units' => $overrides['units'] ?? (int) ($hours * 4),
            'hourly_rate' => $rate,
            'total_amount' => round($hours * $rate, 2),
            'paid_amount' => $overrides['paid_amount'] ?? ($status === BillingClaimAudit::STATUS_PAID ? round($hours * $rate, 2) : null),
            'submission_channel' => '837P - Availity',
            'channel_subtext' => 'MCO',
            'payer_type' => 'MCO',
            'health_plan_name' => $overrides['health_plan'] ?? 'Molina Healthcare',
            'medicaid_id' => '4821234567',
            'plan_member_id' => 'MOL-008-44217',
            'authorization_number' => 'PA-2026-0114',
            'authorization_valid_through' => $period->copy()->addMonths(2)->toDateString(),
            'caregiver_relationship' => 'live-in',
            'evv_exempt' => true,
            'claim_status' => $status,
            'status_detail' => $overrides['status_detail'] ?? 'Paid - EOB posted',
            'audit_status' => BillingClaimAudit::AUDIT_NOT_REVIEWED,
            'submitted_at' => $submittedAt,
            'paid_at' => $status === BillingClaimAudit::STATUS_PAID ? $submittedAt->copy()->addDays(7) : null,
            'pdf_path' => 'billing-claims/837p-sample.pdf',
            'lifecycle_events' => [
                ['status' => 'completed', 'title' => 'Compliance form received + hours verified', 'date' => $submittedAt->format('M j, Y'), 'detail' => 'auto-trigger'],
                ['status' => 'completed', 'title' => 'CP-01 gate passed — Apr paid, no balance', 'date' => $submittedAt->format('M j, Y'), 'detail' => ''],
                ['status' => 'completed', 'title' => '837P submitted to Availity', 'date' => $submittedAt->format('M j, Y'), 'detail' => 'accepted [999]'],
                ['status' => 'current', 'title' => 'EOB posted — paid in full $'.number_format($hours * $rate, 2), 'date' => '', 'detail' => ''],
                ['status' => 'pending', 'title' => 'Reconciled to ledger', 'date' => '', 'detail' => 'auto on next close'],
            ],
            'documents' => [
                ['name' => '837P claim PDF', 'path' => 'billing-claims/837p-sample.pdf', 'status' => 'available'],
                ['name' => 'EOB / remittance', 'path' => 'billing-claims/eob-sample.pdf', 'status' => 'available'],
                ['name' => 'Compliance form ('.$period->format('M').')', 'path' => 'billing-claims/compliance.pdf', 'status' => 'available'],
                ['name' => 'PA-2026-0114', 'path' => 'billing-claims/pa-2026-0114.pdf', 'status' => 'available'],
            ],
        ];
    }

    private function dhsClaimData(int $orgId, ?int $clientId, ?int $employeeId, Carbon $period, array $overrides = []): array
    {
        $hours = $overrides['hours'] ?? 92.0;
        $rate = $overrides['rate'] ?? 27.00;
        $status = $overrides['status'] ?? BillingClaimAudit::STATUS_AWAITING_PAYMENT;
        $periodEnd = $period->copy()->endOfMonth();
        $submittedAt = $overrides['submitted_at'] ?? $periodEnd->copy();

        return [
            'organization_id' => $orgId,
            'client_id' => $clientId,
            'employee_id' => $employeeId,
            'program_type' => BillingClaimAudit::PROGRAM_DHS,
            'billing_period' => $period->toDateString(),
            'period_start' => $period->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'total_hours' => $hours,
            'total_days' => 18,
            'days_required_per_week' => 4,
            'days_met_status' => 'met - no proration',
            'service_description' => 'Personal Care (+ scheduling)',
            'hourly_rate' => $rate,
            'total_amount' => round($hours * $rate, 2),
            'submission_channel' => 'Home Help - Sigma Portal',
            'channel_subtext' => 'MDHHS',
            'payer_type' => 'MDHHS',
            'health_plan_name' => 'DHS Home Help (Full Medicaid)',
            'medicaid_id' => '3307654321',
            'authorization_description' => 'Time/Task Sheet - 4 days/wk (no expiry)',
            'authorizing_worker_name' => 'ASW Denise Carter - MDHHS',
            'caregiver_relationship' => 'live-in spouse',
            'evv_exempt' => true,
            'claim_status' => $status,
            'status_detail' => $overrides['status_detail'] ?? 'Awaiting Sigma posting',
            'audit_status' => BillingClaimAudit::AUDIT_NOT_REVIEWED,
            'submitted_at' => $submittedAt,
            'pdf_path' => 'billing-claims/home-help-invoice.pdf',
            'lifecycle_events' => [
                ['status' => 'completed', 'title' => 'Compliance form received + hours verified', 'date' => $submittedAt->format('M j, Y'), 'detail' => 'auto-trigger'],
                ['status' => 'completed', 'title' => 'CP-01 gate passed — Apr Sigma payment cleared', 'date' => $submittedAt->format('M j, Y'), 'detail' => ''],
                ['status' => 'completed', 'title' => 'Home Help invoice emailed to ASW', 'date' => $submittedAt->format('M j, Y'), 'detail' => 'to Denise Carter'],
                ['status' => 'current', 'title' => 'Awaiting Sigma Portal posting', 'date' => '', 'detail' => 'posts Tue/Wed → paid Friday'],
                ['status' => 'pending', 'title' => 'Sigma Portal PDF confirmation + reconcile on payment', 'date' => '', 'detail' => ''],
            ],
            'documents' => [
                ['name' => 'Home Help Invoice PDF', 'path' => 'billing-claims/home-help-invoice.pdf', 'status' => 'available'],
                ['name' => 'Email to ASW (sent)', 'path' => 'billing-claims/email-asw.eml', 'status' => 'available'],
                ['name' => 'Sigma Portal confirmation', 'path' => null, 'status' => 'pending'],
                ['name' => 'Compliance form ('.$period->format('M').')', 'path' => 'billing-claims/compliance.pdf', 'status' => 'available'],
                ['name' => 'Time/Task Sheet', 'path' => 'billing-claims/time-task-sheet.pdf', 'status' => 'available'],
            ],
        ];
    }
}

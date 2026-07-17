<?php

namespace Database\Factories;

use App\Models\BillingClaimAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingClaimAuditFactory extends Factory
{
    protected $model = BillingClaimAudit::class;

    public function definition(): array
    {
        $program = fake()->randomElement(BillingClaimAudit::programTypes());
        $periodStart = fake()->dateTimeBetween('-6 months', '-1 month');
        $periodEnd = (clone $periodStart)->modify('+1 month -1 day');
        $billingPeriod = (clone $periodStart)->modify('first day of this month');
        $hours = fake()->randomFloat(1, 40, 120);
        $rate = $program === BillingClaimAudit::PROGRAM_MICH ? 30.00 : 27.00;
        $amount = round($hours * $rate, 2);
        $status = fake()->randomElement(BillingClaimAudit::claimStatuses());
        $submittedAt = fake()->dateTimeBetween($periodStart, 'now');

        return [
            'organization_id' => 1,
            'client_id' => 1,
            'employee_id' => null,
            'claim_number' => ($program === BillingClaimAudit::PROGRAM_MICH ? '837P-' : 'HH-')
                .date('Y-m', strtotime($billingPeriod->format('Y-m-d')))
                .'-'.fake()->unique()->numerify('####'),
            'program_type' => $program,
            'billing_period' => $billingPeriod->format('Y-m-d'),
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            'total_hours' => $hours,
            'total_days' => $program === BillingClaimAudit::PROGRAM_DHS ? fake()->numberBetween(16, 22) : null,
            'days_required_per_week' => $program === BillingClaimAudit::PROGRAM_DHS ? 4 : null,
            'days_met_status' => $program === BillingClaimAudit::PROGRAM_DHS ? 'met - no proration' : null,
            'service_code' => $program === BillingClaimAudit::PROGRAM_MICH ? 'T019' : null,
            'service_description' => $program === BillingClaimAudit::PROGRAM_MICH
                ? 'Personal care services'
                : 'Personal Care (+ scheduling)',
            'units' => $program === BillingClaimAudit::PROGRAM_MICH ? (int) ($hours * 4) : null,
            'hourly_rate' => $rate,
            'total_amount' => $amount,
            'paid_amount' => $status === BillingClaimAudit::STATUS_PAID ? $amount : null,
            'submission_channel' => $program === BillingClaimAudit::PROGRAM_MICH
                ? '837P - Availity'
                : 'Home Help - Sigma Portal',
            'channel_subtext' => $program === BillingClaimAudit::PROGRAM_MICH ? 'MCO' : 'MDHHS',
            'payer_type' => $program === BillingClaimAudit::PROGRAM_MICH ? 'MCO' : 'MDHHS',
            'health_plan_name' => $program === BillingClaimAudit::PROGRAM_MICH
                ? fake()->randomElement(['Molina Healthcare', 'Aetna Better Health', 'UnitedHealthcare'])
                : 'DHS Home Help (Full Medicaid)',
            'medicaid_id' => fake()->numerify('##########'),
            'plan_member_id' => $program === BillingClaimAudit::PROGRAM_MICH
                ? 'MOL-'.fake()->numerify('###-#####')
                : null,
            'authorization_number' => $program === BillingClaimAudit::PROGRAM_MICH
                ? 'PA-'.date('Y').'-'.fake()->numerify('####')
                : null,
            'authorization_valid_through' => $program === BillingClaimAudit::PROGRAM_MICH
                ? fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d')
                : null,
            'authorization_description' => $program === BillingClaimAudit::PROGRAM_DHS
                ? 'Time/Task Sheet - 4 days/wk (no expiry)'
                : null,
            'authorizing_worker_name' => $program === BillingClaimAudit::PROGRAM_DHS
                ? 'ASW '.fake()->name().' - MDHHS'
                : null,
            'caregiver_relationship' => fake()->randomElement(['live-in', 'live-in spouse', 'EVV-exempt']),
            'evv_exempt' => true,
            'claim_status' => $status,
            'status_detail' => match ($status) {
                BillingClaimAudit::STATUS_PAID => 'Paid - EOB posted',
                BillingClaimAudit::STATUS_AWAITING_PAYMENT => fake()->randomElement([
                    'Awaiting Sigma posting',
                    'EOB pending',
                    'awaiting EOB',
                ]),
                BillingClaimAudit::STATUS_ON_HOLD => 'On hold (CP-01)',
                BillingClaimAudit::STATUS_REJECTED => 'Rejected - re-submit needed',
                default => 'Submitted',
            },
            'hold_reason' => $status === BillingClaimAudit::STATUS_ON_HOLD ? 'CP-01 prior balance' : null,
            'audit_status' => BillingClaimAudit::AUDIT_NOT_REVIEWED,
            'submitted_at' => $submittedAt,
            'paid_at' => $status === BillingClaimAudit::STATUS_PAID ? fake()->dateTimeBetween($submittedAt, 'now') : null,
            'lifecycle_events' => [],
            'documents' => [],
        ];
    }

    public function mich(): static
    {
        return $this->state(fn () => ['program_type' => BillingClaimAudit::PROGRAM_MICH]);
    }

    public function dhs(): static
    {
        return $this->state(fn () => ['program_type' => BillingClaimAudit::PROGRAM_DHS]);
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'claim_status' => BillingClaimAudit::STATUS_PAID,
                'status_detail' => 'Paid - EOB posted',
                'paid_amount' => $attributes['total_amount'] ?? 1000,
                'paid_at' => now(),
            ];
        });
    }

    public function onHold(): static
    {
        return $this->state([
            'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
            'status_detail' => 'On hold (CP-01)',
            'hold_reason' => 'CP-01 prior balance',
        ]);
    }

    public function awaitingPayment(): static
    {
        return $this->state([
            'claim_status' => BillingClaimAudit::STATUS_AWAITING_PAYMENT,
            'status_detail' => 'Awaiting Sigma posting',
        ]);
    }

    public function forOrganization(int $organizationId): static
    {
        return $this->state(['organization_id' => $organizationId]);
    }
}

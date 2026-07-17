<?php

namespace Database\Seeders;

use App\Models\Billing;
use App\Models\Client;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org = $this->organization();

        $client = fn (string $memberId) => Client::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('member_id', $memberId)
            ->first();

        $billingRows = [
            ['member_id' => 'MD-100001', 'invoice_number' => 'INV-2026-0001', 'period_start' => '2026-03-01', 'period_end' => '2026-03-31', 'total_amount' => 592.00, 'status' => 'Paid'],
            ['member_id' => 'MD-100002', 'invoice_number' => 'INV-2026-0002', 'period_start' => '2026-03-01', 'period_end' => '2026-03-31', 'total_amount' => 448.00, 'status' => 'Sent'],
            ['member_id' => 'MD-100003', 'invoice_number' => 'INV-2026-0003', 'period_start' => '2026-03-01', 'period_end' => '2026-03-31', 'total_amount' => 608.00, 'status' => 'Pending'],
            ['member_id' => 'MD-100004', 'invoice_number' => 'INV-2026-0004', 'period_start' => '2026-03-01', 'period_end' => '2026-03-31', 'total_amount' => 288.00, 'status' => 'Paid'],
            ['member_id' => 'MD-100006', 'invoice_number' => 'INV-2026-0005', 'period_start' => '2026-03-01', 'period_end' => '2026-03-31', 'total_amount' => 639.20, 'status' => 'Sent'],
            ['member_id' => 'MD-100001', 'invoice_number' => 'INV-2026-0006', 'period_start' => '2026-04-01', 'period_end' => '2026-04-07', 'total_amount' => 148.00, 'status' => 'Pending'],
            ['member_id' => 'MD-100003', 'invoice_number' => 'INV-2026-0007', 'period_start' => '2026-04-01', 'period_end' => '2026-04-07', 'total_amount' => 152.00, 'status' => 'Pending'],
        ];

        foreach ($billingRows as $row) {
            $clientModel = $client($row['member_id']);

            if (! $clientModel) {
                continue;
            }

            Billing::updateOrCreate(
                ['invoice_number' => $row['invoice_number']],
                [
                    'organization_id' => $org->id,
                    'client_id' => $clientModel->id,
                    'period_start' => $row['period_start'],
                    'period_end' => $row['period_end'],
                    'total_amount' => $row['total_amount'],
                    'status' => $row['status'],
                ]
            );
        }

        $this->command?->info('Billings seeded.');
    }
}

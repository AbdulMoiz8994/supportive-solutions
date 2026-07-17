<?php

namespace Database\Seeders;

use App\Models\CareDetail;
use App\Models\Client;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

class CareDetailSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org = $this->organization();

        $activeClients = Client::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'Active')
            ->orderBy('id')
            ->get();

        $careDetailData = [
            ['billing_code' => 'T019', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'total_units' => 112, 'authorized_by' => 'Dr. Ahmed Hassan'],
            ['billing_code' => 'T019', 'start_date' => '2026-02-01', 'end_date' => '2026-07-31', 'total_units' => 84, 'authorized_by' => 'Dr. Sarah Malik'],
            ['billing_code' => 'T019', 'start_date' => '2026-01-15', 'end_date' => '2026-06-15', 'total_units' => 140, 'authorized_by' => 'Dr. Nadia Khalil'],
            ['billing_code' => 'T019', 'start_date' => '2026-03-01', 'end_date' => '2026-08-31', 'total_units' => 56, 'authorized_by' => 'Dr. James Owens'],
            ['billing_code' => 'T019', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'total_units' => 168, 'authorized_by' => 'Dr. Emily Chen'],
        ];

        foreach ($activeClients->take(5) as $index => $client) {
            if (! isset($careDetailData[$index])) {
                continue;
            }

            $detail = $careDetailData[$index];

            CareDetail::updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'client_id' => $client->id,
                    'billing_code' => $detail['billing_code'],
                    'start_date' => $detail['start_date'],
                ],
                array_merge($detail, [
                    'organization_id' => $org->id,
                    'client_id' => $client->id,
                    'hours_per_week' => $detail['total_units'] / 4,
                    'status' => 'Active',
                ])
            );
        }

        $this->command?->info('Care details seeded.');
    }
}

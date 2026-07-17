<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Intake;
use App\Models\User;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

class ActivityLogSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org = $this->organization();
        $adminUser = User::where('email', 'admin@beydountech.com')->first();

        $client = Client::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('member_id', 'MD-100001')
            ->first();

        $intake = Intake::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('email', 'alice.g@gmail.com')
            ->first();

        $logs = [];

        if ($client) {
            $logs[] = [
                'action' => 'Created',
                'subject_type' => Client::class,
                'subject_id' => $client->id,
                'description' => 'Client record created during seed.',
                'properties' => ['member_id' => $client->member_id],
            ];
            $logs[] = [
                'action' => 'Updated',
                'subject_type' => Client::class,
                'subject_id' => $client->id,
                'description' => 'Client billing rate updated.',
                'properties' => ['billing_rate' => $client->billing_rate],
            ];
        }

        if ($intake) {
            $logs[] = [
                'action' => 'Created',
                'subject_type' => Intake::class,
                'subject_id' => $intake->id,
                'description' => 'New intake lead received via referral.',
                'properties' => ['source' => $intake->source],
            ];
        }

        foreach ($logs as $log) {
            ActivityLog::updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'action' => $log['action'],
                    'subject_type' => $log['subject_type'],
                    'subject_id' => $log['subject_id'],
                    'description' => $log['description'],
                ],
                array_merge($log, [
                    'organization_id' => $org->id,
                    'user_id' => $adminUser?->id,
                    'ip_address' => '127.0.0.1',
                ])
            );
        }

        $this->command?->info('Activity logs seeded.');
    }
}

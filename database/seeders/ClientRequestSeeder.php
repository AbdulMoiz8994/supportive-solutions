<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\Contact;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

class ClientRequestSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org = $this->organization();

        $client = Client::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('member_id', 'MD-100001')
            ->first();

        $coordinator = Contact::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('email', 'c.johnson@county.gov')
            ->first();

        if (! $client) {
            $this->command?->warn('ClientRequestSeeder skipped: client not found.');

            return;
        }

        $requests = [
            [
                'template' => 'POC',
                'method' => 'Email',
                'notes' => 'Plan of care renewal for Q2 2026.',
                'status' => 'Sent',
                'sent_at' => now()->subDays(5),
            ],
            [
                'template' => 'Auth',
                'method' => 'Fax',
                'notes' => 'Prior authorization extension request.',
                'status' => 'Pending',
                'sent_at' => null,
            ],
            [
                'template' => 'Notes',
                'method' => 'Email',
                'notes' => 'Weekly progress notes for case coordinator review.',
                'status' => 'Sent',
                'sent_at' => now()->subDays(2),
            ],
        ];

        foreach ($requests as $request) {
            ClientRequest::updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'client_id' => $client->id,
                    'template' => $request['template'],
                    'method' => $request['method'],
                ],
                array_merge($request, [
                    'organization_id' => $org->id,
                    'client_id' => $client->id,
                    'coordinator_id' => $coordinator?->id,
                ])
            );
        }

        $this->command?->info('Client requests seeded.');
    }
}

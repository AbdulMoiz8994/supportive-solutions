<?php

namespace Database\Seeders;

use App\Models\Message;
use App\Models\User;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org = $this->organization();

        $adminUser = User::where('email', 'admin@beydountech.com')->first();
        $staffUser = User::where('email', 'staff@beydountech.com')->first();
        $careUser = User::where('email', 'caregiver@beydountech.com')->first();

        if (! $adminUser || ! $staffUser || ! $careUser) {
            $this->command?->warn('MessageSeeder skipped: required users not found.');

            return;
        }

        $messages = [
            ['sender_id' => $adminUser->id, 'receiver_id' => $staffUser->id, 'content' => 'Good morning! Please confirm all schedules for this week are finalized.', 'read_at' => now()->subHours(3)],
            ['sender_id' => $staffUser->id, 'receiver_id' => $adminUser->id, 'content' => 'Yes, everything is set. John Doe and Jane Smith visits are confirmed for Monday.', 'read_at' => now()->subHours(2)],
            ['sender_id' => $adminUser->id, 'receiver_id' => $staffUser->id, 'content' => 'Great. Also please follow up with Robert Johnson regarding his care detail renewal.', 'read_at' => null],
            ['sender_id' => $adminUser->id, 'receiver_id' => $careUser->id, 'content' => 'Reminder: Your visit with John Doe is tomorrow at 8:00 AM. Please clock in on time.', 'read_at' => now()->subHour()],
            ['sender_id' => $careUser->id, 'receiver_id' => $adminUser->id, 'content' => 'Understood! I will be there. Should I bring the assessment form?', 'read_at' => now()->subMinutes(30)],
            ['sender_id' => $adminUser->id, 'receiver_id' => $careUser->id, 'content' => 'Yes, please bring the T019 authorization form and have the client sign it.', 'read_at' => null],
            ['sender_id' => $staffUser->id, 'receiver_id' => $careUser->id, 'content' => 'Kevin is on leave this week. Can you cover his visit with Linda Wilson on Thursday?', 'read_at' => null],
        ];

        foreach ($messages as $index => $message) {
            Message::updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'sender_id' => $message['sender_id'],
                    'receiver_id' => $message['receiver_id'],
                    'content' => $message['content'],
                ],
                array_merge($message, [
                    'organization_id' => $org->id,
                    'created_at' => now()->subMinutes(300 - ($index * 40)),
                ])
            );
        }

        $this->command?->info('Messages seeded.');
    }
}

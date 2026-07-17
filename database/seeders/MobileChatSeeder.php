<?php

namespace Database\Seeders;

use App\Models\SecureMessageThread;
use App\Models\User;
use App\Services\Communication\SecureMessageService;
use Illuminate\Database\Seeder;

/**
 * Seeds a couple of secure-message threads so the mobile team can open the
 * Inbox/Chat screens and test two-sided messaging out of the box.
 *
 * These use SecureMessageThread — the same store the mobile /conversations API
 * reads — NOT the legacy `Message` model in MessageSeeder (that one backs the
 * old web direct-message view). Idempotent: skips threads it already created.
 */
class MobileChatSeeder extends Seeder
{
    public function run(): void
    {
        $care1 = User::where('email', 'caregiver@beydountech.com')->first();
        $care2 = User::where('email', 'caregiver2@beydountech.com')->first();
        $office = User::where('email', 'staff@beydountech.com')->first();

        if (! $care1 || ! $care2) {
            $this->command?->warn('MobileChatSeeder skipped: caregiver users not found.');

            return;
        }

        $service = app(SecureMessageService::class);

        // Office → caregiver: gives the caregiver an inbox item on first login.
        if ($office) {
            $this->ensureThread($service, $office, 'Welcome to the team!', [$care1->id], [
                [$office, 'Hi! This inbox is your secure line to the office — reach out any time you need us.'],
                [$care1, 'Thank you! Good to know.'],
            ]);
        }

        // Caregiver ↔ caregiver: the two-sided thread for testing live chat
        // from two mobile logins (caregiver@ and caregiver2@, both care123).
        $this->ensureThread($service, $care2, 'Covering Thursday?', [$care1->id], [
            [$care2, 'Hey, can you cover my Thursday visit with Robert Lee? I have a doctor appointment.'],
            [$care1, 'Sure, I can take it. Send me the address.'],
            [$care2, 'Thanks so much! 248 Oak Street, Brooklyn — 6:00 PM.'],
        ]);

        $this->command?->info('Mobile chat demo threads seeded.');
    }

    /**
     * Create a thread from its first message, then post the follow-up replies.
     * No-op if a thread with the same subject + creator already exists.
     *
     * @param  array<int, int>  $participantIds  the OTHER participants (creator is added automatically)
     * @param  array<int, array{0: User, 1: string}>  $messages  [sender, body] pairs, first one opens the thread
     */
    private function ensureThread(
        SecureMessageService $service,
        User $creator,
        string $subject,
        array $participantIds,
        array $messages
    ): void {
        $exists = SecureMessageThread::query()
            ->where('subject', $subject)
            ->where('created_by', $creator->id)
            ->exists();

        if ($exists || $messages === []) {
            return;
        }

        [, $firstBody] = $messages[0];
        $thread = $service->createThread($creator, $subject, $firstBody, $participantIds);

        foreach (array_slice($messages, 1) as [$sender, $body]) {
            $service->reply($thread, $sender, $body);
        }
    }
}

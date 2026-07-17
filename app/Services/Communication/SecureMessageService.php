<?php

namespace App\Services\Communication;

use App\Events\MessageSent;
use App\Models\Client;
use App\Models\Communication;
use App\Models\Employee;
use App\Models\SecureMessage;
use App\Models\SecureMessageParticipant;
use App\Models\SecureMessageThread;
use App\Models\User;
use App\Support\CommunicationOrganizationResolver;
use Illuminate\Support\Facades\DB;

class SecureMessageService
{
    public function __construct(
        protected CommunicationNotificationService $notificationService
    ) {}

    /**
     * @param  array<int, int>  $participantUserIds
     */
    public function createThread(
        User $creator,
        string $subject,
        string $body,
        array $participantUserIds,
        ?Client $relatedClient = null,
        ?Employee $relatedEmployee = null
    ): SecureMessageThread {
        $participantUserIds = array_unique(array_merge($participantUserIds, [$creator->id]));

        $this->assertParticipantsInOrganization($creator, $participantUserIds);

        $thread = DB::transaction(function () use ($creator, $subject, $body, $participantUserIds, $relatedClient, $relatedEmployee) {
            $organizationId = CommunicationOrganizationResolver::resolve($creator, $relatedClient, null, $relatedEmployee);

            $thread = SecureMessageThread::create([
                'organization_id' => $organizationId,
                'subject' => $subject,
                'related_type' => $relatedClient ? Client::class : ($relatedEmployee ? Employee::class : null),
                'related_id' => $relatedClient?->id ?? $relatedEmployee?->id,
                'created_by' => $creator->id,
                'last_message_at' => now(),
            ]);

            foreach ($participantUserIds as $userId) {
                SecureMessageParticipant::create([
                    'thread_id' => $thread->id,
                    'user_id' => $userId,
                    'last_read_at' => (int) $userId === (int) $creator->id ? now() : null,
                ]);
            }

            $message = SecureMessage::create([
                'thread_id' => $thread->id,
                'sender_id' => $creator->id,
                'body' => $body,
            ]);

            Communication::create([
                'organization_id' => $organizationId,
                'related_type' => $relatedClient ? Client::class : ($relatedEmployee ? Employee::class : null),
                'related_id' => $relatedClient?->id ?? $relatedEmployee?->id,
                'channel' => Communication::CHANNEL_INTERNAL_MESSAGE,
                'direction' => Communication::DIRECTION_INTERNAL,
                'subject' => $subject,
                'body' => null,
                'status' => Communication::STATUS_SENT,
                'sender_id' => $creator->id,
                'metadata' => ['secure_message_thread_id' => $thread->id, 'secure_message_id' => $message->id],
                'sent_at' => now(),
            ]);

            foreach ($participantUserIds as $userId) {
                if ((int) $userId === (int) $creator->id) {
                    continue;
                }

                $recipient = User::find($userId);
                if ($recipient) {
                    $this->notificationService->notifySecureMessage($recipient, $thread);
                }
            }

            return $thread->load(['participants.user', 'messages.sender']);
        });

        // Real-time: notify every participant except the creator (open chats +
        // inbox badges). Queued via ShouldBroadcast, so a socket outage can
        // never break thread creation.
        $firstMessage = $thread->messages->first();
        if ($firstMessage) {
            MessageSent::dispatch($firstMessage, collect($participantUserIds)
                ->reject(fn ($id) => (int) $id === (int) $creator->id)
                ->values()
                ->all());
        }

        return $thread;
    }

    public function reply(SecureMessageThread $thread, User $sender, string $body): SecureMessage
    {
        $this->assertParticipant($thread, $sender);

        $message = DB::transaction(function () use ($thread, $sender, $body) {
            $message = SecureMessage::create([
                'thread_id' => $thread->id,
                'sender_id' => $sender->id,
                'body' => $body,
            ]);

            $thread->update(['last_message_at' => now()]);

            SecureMessageParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', '!=', $sender->id)
                ->update(['last_read_at' => null]);

            SecureMessageParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $sender->id)
                ->update(['last_read_at' => now()]);

            Communication::create([
                'organization_id' => $thread->organization_id,
                'related_type' => $thread->related_type,
                'related_id' => $thread->related_id,
                'channel' => Communication::CHANNEL_INTERNAL_MESSAGE,
                'direction' => Communication::DIRECTION_INTERNAL,
                'subject' => $thread->subject,
                'body' => null,
                'status' => Communication::STATUS_SENT,
                'sender_id' => $sender->id,
                'metadata' => ['secure_message_thread_id' => $thread->id, 'secure_message_id' => $message->id],
                'sent_at' => now(),
            ]);

            $thread->participants()
                ->where('user_id', '!=', $sender->id)
                ->with('user')
                ->get()
                ->each(function (SecureMessageParticipant $participant) use ($thread) {
                    if ($participant->user) {
                        $this->notificationService->notifySecureMessage($participant->user, $thread);
                    }
                });

            return $message->load('sender');
        });

        // Real-time fan-out to the other participants (queued; never blocks the reply).
        MessageSent::dispatch($message, $thread->participants()
            ->where('user_id', '!=', $sender->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all());

        return $message;
    }

    public function markThreadRead(SecureMessageThread $thread, User $user): void
    {
        $this->assertParticipant($thread, $user);

        SecureMessageParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }

    public function assertParticipant(SecureMessageThread $thread, User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if (! $user->hasPermission('manage_secure_messages')) {
            $isParticipant = SecureMessageParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $user->id)
                ->exists();

            if (! $isParticipant) {
                abort(403);
            }
        }
    }

    /**
     * @param  array<int, int>  $participantUserIds
     */
    protected function assertParticipantsInOrganization(User $creator, array $participantUserIds): void
    {
        $count = User::query()
            ->whereIn('id', $participantUserIds)
            ->where('organization_id', $creator->organization_id)
            ->count();

        if ($count !== count($participantUserIds)) {
            abort(403, 'All participants must belong to your organization.');
        }
    }
}

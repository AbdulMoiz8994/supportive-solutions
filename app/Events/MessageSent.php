<?php

namespace App\Events;

use App\Models\SecureMessage;
use App\Support\Api\AvatarUrl;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a secure message is created (new thread or reply). Broadcasts to
 * the conversation channel (open chat screens update live) and to each other
 * participant's personal channel (inbox badge / new-thread ping).
 *
 * Payload intentionally omits `is_mine`: broadcasting is one-to-many, so the
 * client decides that by comparing `sender_id` to its own user id.
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $threadId;

    /** @var array<int, int> */
    public array $recipientUserIds;

    /** @var array<string, mixed> */
    public array $payload;

    /**
     * @param  array<int, int>  $recipientUserIds  participants other than the sender
     */
    public function __construct(SecureMessage $message, array $recipientUserIds)
    {
        $message->loadMissing('sender.employee');

        $this->threadId = (int) $message->thread_id;
        $this->recipientUserIds = array_values(array_unique(array_map('intval', $recipientUserIds)));
        $this->payload = [
            'id' => $message->id,
            'thread_id' => (int) $message->thread_id,
            'body' => $message->body,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name,
            'avatar_url' => AvatarUrl::forPhoto($message->sender?->employee?->profile_photo),
            'created_at' => optional($message->created_at)->toIso8601String(),
            'time' => optional($message->created_at)->format('g:i A'),
            'time_ago' => optional($message->created_at)->diffForHumans(),
        ];
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('conversation.'.$this->threadId)];

        foreach ($this->recipientUserIds as $userId) {
            $channels[] = new PrivateChannel('user.'.$userId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}

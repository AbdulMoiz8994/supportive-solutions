<?php

namespace App\Http\Resources\Api;

use App\Models\SecureMessage;
use App\Models\SecureMessageParticipant;
use App\Models\User;
use App\Support\Api\AvatarUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A conversation summary for the inbox list (one row per thread).
 *
 * @mixin \App\Models\SecureMessageThread
 */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $me = $request->user();

        /** @var SecureMessage|null $last */
        $last = $this->latestMessage;

        /** @var SecureMessageParticipant|null $myParticipant */
        $myParticipant = $this->participants
            ->firstWhere('user_id', $me?->id);

        $counterpart = $this->counterpart($me);

        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'counterpart' => $counterpart ? [
                'id' => $counterpart->id,
                'name' => $counterpart->name,
                'avatar_url' => AvatarUrl::forPhoto($counterpart->employee?->profile_photo),
            ] : null,
            'last_message' => $last?->body,
            'last_sender' => $last?->sender?->name,
            'unread' => $myParticipant !== null && $myParticipant->last_read_at === null,
            'last_message_at' => optional($this->last_message_at)->toIso8601String(),
            'time_ago' => optional($this->last_message_at)->diffForHumans(),
        ];
    }

    /**
     * The other party shown in the inbox row: the most recent participant
     * that is not the logged-in user (falls back to the thread creator).
     */
    private function counterpart(?User $me): ?User
    {
        $other = $this->participants
            ->first(fn (SecureMessageParticipant $p) => (int) $p->user_id !== (int) $me?->id);

        return $other?->user ?? $this->creator;
    }
}

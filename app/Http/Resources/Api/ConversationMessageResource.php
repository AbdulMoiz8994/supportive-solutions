<?php

namespace App\Http\Resources\Api;

use App\Support\Api\AvatarUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single message inside a conversation (chat screen bubble).
 *
 * @mixin \App\Models\SecureMessage
 */
class ConversationMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $me = $request->user();
        $sender = $this->sender;

        return [
            'id' => $this->id,
            'body' => $this->body,
            'sender_id' => $this->sender_id,
            'sender_name' => $sender?->name,
            'avatar_url' => AvatarUrl::forPhoto($sender?->employee?->profile_photo),
            'is_mine' => $me !== null && (int) $this->sender_id === (int) $me->id,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'time' => optional($this->created_at)->format('g:i A'),
            'time_ago' => optional($this->created_at)->diffForHumans(),
        ];
    }
}

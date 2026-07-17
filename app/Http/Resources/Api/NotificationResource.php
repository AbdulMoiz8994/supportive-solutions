<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CommunicationNotification
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'title'      => $this->title,
            'body'       => $this->body,
            'read'       => $this->read_at !== null,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'time_ago'   => optional($this->created_at)->diffForHumans(),
        ];
    }
}

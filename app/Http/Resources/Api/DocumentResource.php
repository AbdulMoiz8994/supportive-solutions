<?php

namespace App\Http\Resources\Api;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\Document
 */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $documentable = $this->whenLoaded('documentable');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'category' => $this->category,
            'is_signed' => (bool) $this->is_signed,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'verification_status' => $this->verification_status,
            'attached_to' => $this->attachedToLabel(),
            'client_id' => $this->documentable_type === Client::class ? $this->documentable_id : null,
            'url' => $this->fileUrl(),
            'uploaded_at' => optional($this->created_at)->toIso8601String(),
        ];
    }

    private function attachedToLabel(): ?string
    {
        $target = $this->documentable;

        if (! $target) {
            return null;
        }

        return trim(($target->first_name ?? '').' '.($target->last_name ?? '')) ?: null;
    }

    private function fileUrl(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return ($this->disk ?: 'public') === 'public'
            ? Storage::disk('public')->url($this->path)
            : null;
    }
}

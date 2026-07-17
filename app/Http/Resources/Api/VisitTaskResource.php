<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\VisitTask
 */
class VisitTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'schedule_id' => $this->schedule_id,
            'label' => $this->label,
            'category' => $this->category,
            'sort_order' => $this->sort_order,
            'is_completed' => (bool) $this->is_completed,
            'completed_at' => optional($this->completed_at)->toIso8601String(),
        ];
    }
}

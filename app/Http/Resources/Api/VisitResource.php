<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Schedule
 */
class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'client_id'          => $this->client_id,
            'client_name'        => $this->whenLoaded('client', fn () => trim("{$this->client->first_name} {$this->client->last_name}")),
            'title'              => $this->title,
            'event_type'         => $this->event_type,
            'status'             => $this->status,
            'evv_verified'       => (bool) $this->evv_status,
            'evv_status'         => $this->resolveEvvStatus(),
            'date'               => optional($this->date)->toDateString(),
            'scheduled_start'    => optional($this->start_at)->toIso8601String(),
            'scheduled_end'      => optional($this->end_at)->toIso8601String(),
            'clock_in_at'        => optional($this->actual_clock_in)->toIso8601String(),
            'clock_out_at'       => optional($this->actual_clock_out)->toIso8601String(),
            'total_hours'        => $this->total_hours !== null ? (float) $this->total_hours : null,
            'clock_in_location'  => $this->coordinatePair($this->clock_in_latitude, $this->clock_in_longitude),
            'clock_out_location' => $this->coordinatePair($this->clock_out_latitude, $this->clock_out_longitude),
            'address'            => $this->address,
        ];
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}|null
     */
    private function coordinatePair(mixed $lat, mixed $lng): ?array
    {
        if ($lat === null && $lng === null) {
            return null;
        }

        return [
            'latitude'  => $lat !== null ? (float) $lat : null,
            'longitude' => $lng !== null ? (float) $lng : null,
        ];
    }

    private function resolveEvvStatus(): string
    {
        if ((bool) $this->evv_status) {
            return 'verified';
        }

        if ($this->actual_clock_in && ! $this->actual_clock_out) {
            return 'pending';
        }

        if ($this->status === \App\Models\Schedule::STATUS_COMPLETED && ! $this->evv_status) {
            return 'missing';
        }

        return 'pending';
    }
}

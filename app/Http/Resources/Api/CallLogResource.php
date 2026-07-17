<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CallLog
 */
class CallLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $telDigits = $this->to_number ? preg_replace('/[^0-9+]/', '', $this->to_number) : null;

        return [
            'id'               => $this->id,
            'client_id'        => $this->client_id,
            'client_name'      => $this->client_name,
            'direction'        => $this->direction,
            'mode'             => $this->mode,          // 'ringout' | 'manual'
            'status'           => $this->status,        // 'initiated' | 'manual'
            'to'               => $this->to_number,
            'from'             => $this->from_number,
            'provider'         => $this->provider,      // 'ringcentral' | null
            'provider_call_id' => $this->provider_call_id,
            'provider_error'   => $this->failure_reason,
            // Native-dialer fallback the app opens when mode = 'manual'.
            'tel'              => $telDigits ? 'tel:'.$telDigits : null,
            'created_at'       => optional($this->created_at)->toIso8601String(),
            'time'             => optional($this->created_at)->format('g:i A'),
            'time_ago'         => optional($this->created_at)->diffForHumans(),
        ];
    }
}

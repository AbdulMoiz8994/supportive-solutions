<?php

namespace App\Http\Resources\Api;

use App\Services\PayrollDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PayRecord
 */
class PayRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stubAvailable = app(PayrollDocumentService::class)->stubIsAvailable($this->resource);

        return [
            'id'             => $this->id,
            'period'         => $this->period,
            'period_key'     => $this->period_key,
            'hours'          => $this->hours !== null ? (float) $this->hours : null,
            'rate'           => $this->rate !== null ? (float) $this->rate : null,
            'gross'          => $this->gross !== null ? (float) $this->gross : null,
            'status'         => $this->status,
            'program'        => $this->program_tag,
            'paid_date'      => optional($this->paid_date)->toDateString(),
            'client_name'    => $this->whenLoaded('client', fn () => trim("{$this->client->first_name} {$this->client->last_name}")),
            'stub_available' => $stubAvailable,
            'stub_url'       => $stubAvailable ? route('api.pay.stub', $this->id) : null,
        ];
    }
}

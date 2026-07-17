<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Client
 */
class AssignedClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'first_name'    => $this->first_name,
            'last_name'     => $this->last_name,
            'name'          => trim("{$this->first_name} {$this->last_name}"),
            'phone'         => $this->phone,
            'address'       => $this->address,
            'county'        => $this->county,
            'program'       => $this->program_label,
            'authorization' => $this->authStatus(),
        ];
    }
}

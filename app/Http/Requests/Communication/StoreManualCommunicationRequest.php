<?php

namespace App\Http\Requests\Communication;

use App\Models\Communication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualCommunicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Communication::class);
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in([Communication::CHANNEL_CALL, Communication::CHANNEL_NOTE])],
            'subject' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:10000'],
            'direction' => ['nullable', Rule::in([Communication::DIRECTION_INBOUND, Communication::DIRECTION_OUTBOUND])],
            'related_type' => ['nullable', Rule::in(['Client', 'Employee'])],
            'related_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

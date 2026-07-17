<?php

namespace App\Http\Requests\Client;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientCareDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = Client::withoutGlobalScopes()->findOrFail($this->route('id'));

        return $this->user()->can('addCareDetail', $client);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'billing_code' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'total_units' => ['required', 'integer'],
            'authorized_by' => ['nullable', 'string'],
        ];
    }
}

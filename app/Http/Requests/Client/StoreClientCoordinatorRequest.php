<?php

namespace App\Http\Requests\Client;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientCoordinatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = Client::withoutGlobalScopes()->findOrFail($this->route('id'));

        return $this->user()->can('sendRequest', $client);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'coordinator_id' => ['required'],
            'template' => ['required', 'string'],
            'method' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

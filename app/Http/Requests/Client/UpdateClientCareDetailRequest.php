<?php

namespace App\Http\Requests\Client;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientCareDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = Client::withoutGlobalScopes()->findOrFail($this->route('id'));

        // Editing an existing authorization uses the same right as adding one.
        return $this->user()->can('addCareDetail', $client);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'billing_code' => ['required', 'string', 'max:20'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'total_units' => ['required', 'integer', 'min:0'],
            'authorized_by' => ['nullable', 'string', 'max:255'],
            // Present so the inline edit-panel can bounce back to the right tab/section.
            'section' => ['nullable', 'string'],
            'tab' => ['nullable', 'string'],
        ];
    }
}

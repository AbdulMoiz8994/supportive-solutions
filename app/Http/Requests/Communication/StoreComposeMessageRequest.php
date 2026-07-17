<?php

namespace App\Http\Requests\Communication;

use App\Models\Communication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComposeMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('send', Communication::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_type' => ['required', Rule::in(['client', 'employee', 'contact'])],
            'recipient_id' => ['required', 'integer', 'min:1'],
            'channel' => ['required', Rule::in(['sms', 'email'])],
            'language' => ['required', Rule::in(['en', 'ar'])],
            'subject' => ['nullable', 'string', 'max:255', 'required_if:channel,email'],
            'body' => ['required', 'string', 'max:5000'],
            'template_id' => ['nullable', 'integer', 'exists:communication_templates,id'],
        ];
    }
}

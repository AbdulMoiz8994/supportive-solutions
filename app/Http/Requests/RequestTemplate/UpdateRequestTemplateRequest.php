<?php

namespace App\Http\Requests\RequestTemplate;

use App\Models\RequestTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequestTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = RequestTemplate::withoutGlobalScopes()->findOrFail($this->route('id'));

        return $this->user()->can('update', $template);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $deliveryMethod = $this->input('delivery_method');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'delivery_method' => ['required', Rule::in(RequestTemplate::deliveryMethods())],
            'recipient_type' => ['required', Rule::in(RequestTemplate::recipientTypes())],
            'default_recipient_email' => ['nullable', 'email', 'max:255'],
            'default_recipient_fax' => ['nullable', 'string', 'max:30', 'regex:/^[\d\s\-\+\(\)\.]+$/'],
            'subject' => [
                Rule::requiredIf(in_array($deliveryMethod, [RequestTemplate::DELIVERY_EMAIL, RequestTemplate::DELIVERY_BOTH], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'body' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

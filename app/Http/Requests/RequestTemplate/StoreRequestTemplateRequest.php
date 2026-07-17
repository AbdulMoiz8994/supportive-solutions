<?php

namespace App\Http\Requests\RequestTemplate;

use App\Models\RequestTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequestTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RequestTemplate::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $deliveryMethod = $this->input('delivery_method');

        return [
            'organization_id' => [
                Rule::requiredIf(fn () => $this->user()->isSuperAdmin() && ! $this->user()->organization_id),
                'nullable',
                'integer',
                'exists:organizations,id',
            ],
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

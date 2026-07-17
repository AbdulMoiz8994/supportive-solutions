<?php

namespace App\Http\Requests\Communication;

use App\Models\CommunicationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunicationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CommunicationTemplate $template */
        $template = $this->route('template');

        return $this->user()->can('update', $template);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'channel' => ['required', Rule::in(CommunicationTemplate::channels())],
            'subject' => ['nullable', 'string', 'max:500', 'required_if:channel,email'],
            'body' => ['required', 'string', 'max:20000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'recipient_strategy' => ['required', Rule::in(CommunicationTemplate::recipientStrategies())],
            'default_recipient' => ['nullable', 'string', 'max:255'],
            'allowed_variables' => ['nullable', 'array'],
            'allowed_variables.*' => ['string', Rule::in(config('communications.template_variables'))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }
}

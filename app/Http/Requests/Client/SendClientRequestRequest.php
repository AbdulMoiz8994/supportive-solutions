<?php

namespace App\Http\Requests\Client;

use App\Models\Client;
use App\Models\RequestTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendClientRequestRequest extends FormRequest
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
            'request_template_id' => ['required', 'integer', 'exists:request_templates,id'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'recipient_fax' => ['nullable', 'string', 'max:30', 'regex:/^[\d\s\-\+\(\)\.]+$/'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $client = Client::withoutGlobalScopes()->find($this->route('id'));
            $template = RequestTemplate::withoutGlobalScopes()->find($this->input('request_template_id'));

            if (! $client || ! $template) {
                return;
            }

            if ((int) $template->organization_id !== (int) $client->organization_id) {
                $validator->errors()->add('request_template_id', 'The selected template does not belong to this client organization.');
            }

            if (! $template->is_active) {
                $validator->errors()->add('request_template_id', 'The selected template is inactive.');
            }

            if (in_array($template->recipient_type, [RequestTemplate::RECIPIENT_CUSTOM, RequestTemplate::RECIPIENT_OTHER], true)) {
                if ($this->recipientRequired($template, 'email') && ! $this->input('recipient_email') && ! $template->default_recipient_email) {
                    $validator->errors()->add('recipient_email', 'A recipient email is required for this template.');
                }

                if ($this->recipientRequired($template, 'fax') && ! $this->input('recipient_fax') && ! $template->default_recipient_fax) {
                    $validator->errors()->add('recipient_fax', 'A recipient fax number is required for this template.');
                }
            }
        });
    }

    protected function recipientRequired(RequestTemplate $template, string $channel): bool
    {
        if ($channel === 'email') {
            return in_array($template->delivery_method, [RequestTemplate::DELIVERY_EMAIL, RequestTemplate::DELIVERY_BOTH], true);
        }

        return in_array($template->delivery_method, [RequestTemplate::DELIVERY_FAX, RequestTemplate::DELIVERY_BOTH], true);
    }
}

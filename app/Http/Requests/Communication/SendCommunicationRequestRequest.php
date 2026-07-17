<?php

namespace App\Http\Requests\Communication;

use App\Models\Client;
use App\Models\Communication;
use App\Models\CommunicationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendCommunicationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('send', Communication::class);
    }

    public function rules(): array
    {
        $maxKb = (int) config('communications.max_attachment_kilobytes', 10240);
        $mimes = implode(',', config('communications.allowed_attachment_mimes', []));

        return [
            'template_id' => ['required', 'integer', Rule::exists('communication_templates', 'id')->whereNull('deleted_at')],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'subject' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:20000'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'recipient_fax' => ['nullable', 'string', 'max:50'],
            'recipient_phone' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', "max:{$maxKb}", "mimes:{$mimes}"],
        ];
    }

    public function client(): ?Client
    {
        $clientId = $this->input('client_id')
            ?? $this->route('client')
            ?? $this->route('id');

        return $clientId ? Client::find($clientId) : null;
    }

    public function template(): CommunicationTemplate
    {
        return CommunicationTemplate::findOrFail($this->integer('template_id'));
    }
}

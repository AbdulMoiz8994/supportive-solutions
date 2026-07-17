<?php

namespace App\Http\Requests\Directory\Concerns;

use App\Models\Contact;
use Illuminate\Validation\Rule;

trait ValidatesContactFields
{
    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function contactFieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(Contact::types())],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'clinic_name' => ['nullable', 'string', 'max:255'],
            'provider_id' => ['nullable', 'string', 'max:100'],
            'claim_channel' => ['nullable', 'string', Rule::in(Contact::claimChannels())],
            'contracted_rate' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'parent_contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:50'],
            'county' => ['nullable', 'string', 'max:100'],
            'zip' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'integration_slug' => ['nullable', 'string', 'max:100'],
            'integration_credential_key' => ['nullable', 'string', 'max:100'],
            'data_flow' => ['nullable', 'string', 'max:1000'],
            'app_area' => ['nullable', 'string', 'max:100'],
            'owning_agent' => ['nullable', 'string', 'max:255'],
        ];
    }
}

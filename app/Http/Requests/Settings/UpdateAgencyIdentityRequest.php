<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class UpdateAgencyIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'agency_npi' => ['nullable', 'string', 'max:20'],
            'tax_id_ein' => ['nullable', 'string', 'max:20'],
            'medicaid_provider_id' => ['nullable', 'string', 'max:30'],
            'legal_business_name' => ['nullable', 'string', 'max:255'],
            'legal_address_street' => ['nullable', 'string', 'max:255'],
            'legal_address_city' => ['nullable', 'string', 'max:100'],
            'legal_address_state' => ['nullable', 'string', 'size:2'],
            'legal_address_zip' => ['nullable', 'string', 'max:10'],
            'main_phone' => ['nullable', 'string', 'max:30'],
            'efax_number' => ['nullable', 'string', 'max:30'],
            'service_state' => ['nullable', 'string', 'size:2'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw (new ValidationException($validator))
            ->redirectTo(route('settings.global', ['tab' => 'agency-profile']));
    }
}

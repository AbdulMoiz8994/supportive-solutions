<?php

namespace App\Http\Requests\Settings;

use App\Models\IntegrationCredential;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateCredentialVaultRequest extends FormRequest
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
        $keys = array_keys(IntegrationCredential::supportedKeys());

        return [
            'credentials' => ['required', 'array'],
            'credentials.*.key' => ['required', 'string', Rule::in($keys)],
            'credentials.*.username' => ['nullable', 'string', 'max:255'],
            'credentials.*.password' => ['nullable', 'string', 'max:500'],
            'credentials.*.api_key' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata' => ['nullable', 'array'],
            'credentials.*.metadata.env' => ['nullable', Rule::in(['demo', 'production'])],
            'credentials.*.metadata.attestation_status' => ['nullable', Rule::in(['pending', 'approved'])],
            'credentials.*.metadata.token_cache_seconds' => ['nullable', 'integer', 'min:60', 'max:3600'],
            'credentials.*.metadata.timeout' => ['nullable', 'integer', 'min:5', 'max:120'],
            'credentials.*.metadata.app_id' => ['nullable', 'string', 'max:255'],
            'credentials.*.metadata.demo_key' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.demo_secret' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.prod_key' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.prod_secret' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.token_url' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.api_base_url' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.scope_demo' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.scope_prod' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.request_type' => ['nullable', 'string', 'max:100'],
            'credentials.*.metadata.default_payer_id' => ['nullable', 'string', 'max:50'],
            'credentials.*.metadata.default_diagnosis_code' => ['nullable', 'string', 'max:20'],
            'credentials.*.metadata.place_of_service' => ['nullable', 'string', 'max:10'],
            'credentials.*.metadata.patient_relationship' => ['nullable', 'string', 'max:10'],
            'credentials.*.metadata.mock_scenario_id' => ['nullable', 'string', 'max:100'],
            'credentials.*.metadata.portal_url' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.api_url' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.api_key' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.accountant_email' => ['nullable', 'email', 'max:255'],
            'credentials.*.metadata.client_id' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.client_secret' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.environment' => ['nullable', Rule::in(['implementation', 'production'])],
            'credentials.*.metadata.token_url' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.scope' => ['nullable', 'string', 'max:255'],
            'credentials.*.metadata.provider_tax_id' => ['nullable', 'string', 'max:20'],
            'credentials.*.metadata.office_npi' => ['nullable', 'string', 'max:20'],
            'credentials.*.metadata.payer_id' => ['nullable', 'string', 'max:50'],
            'credentials.*.metadata.server_url' => ['nullable', 'string', 'max:500'],
            'credentials.*.metadata.extension' => ['nullable', 'string', 'max:50'],
            'credentials.*.metadata.from_number' => ['nullable', 'string', 'max:50'],
            'credentials.*.metadata.jwt' => ['nullable', 'string', 'max:10000'],
            'credentials.*.metadata.refresh_token' => ['nullable', 'string', 'max:2000'],
            'credentials.*.metadata.delegated_user' => ['nullable', 'email', 'max:255'],
            'credentials.*.metadata.default_asw_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('credentials', []) as $index => $entry) {
                if (($entry['key'] ?? '') !== IntegrationCredential::KEY_RINGCENTRAL) {
                    continue;
                }

                $jwt = trim((string) data_get($entry, 'metadata.jwt', ''));
                $isConfiguringRingCentral = collect([
                    data_get($entry, 'metadata.client_id'),
                    data_get($entry, 'metadata.client_secret'),
                    data_get($entry, 'metadata.server_url'),
                ])->contains(fn ($value) => filled($value));

                if ($isConfiguringRingCentral && $jwt === '') {
                    $validator->errors()->add(
                        "credentials.{$index}.metadata.jwt",
                        'JWT is required for RingCentral.'
                    );
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        $integration = data_get($this->input('credentials'), '0.key');

        throw (new ValidationException($validator))
            ->redirectTo(route('settings.global', array_filter([
                'tab' => 'credential-vault',
                'integration' => $integration,
            ])));
    }
}

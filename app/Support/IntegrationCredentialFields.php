<?php

namespace App\Support;

use App\Models\IntegrationCredential;

class IntegrationCredentialFields
{
    /**
     * @return list<string>
     */
    public static function secretMetadataKeys(string $integrationKey): array
    {
        return match ($integrationKey) {
            IntegrationCredential::KEY_AVAILITY => ['demo_key', 'demo_secret', 'prod_key', 'prod_secret'],
            IntegrationCredential::KEY_ACCOUNTANTSWORLD => ['oauth_client_secret'],
            IntegrationCredential::KEY_HHA => [],
            IntegrationCredential::KEY_RINGCENTRAL => [],
            IntegrationCredential::KEY_GOOGLE_WORKSPACE => [],
            default => [],
        };
    }

    /**
     * Default metadata values for forms (non-secret), merged with stored metadata.
     *
     * @return array<string, mixed>
     */
    public static function defaults(string $integrationKey): array
    {
        return match ($integrationKey) {
            IntegrationCredential::KEY_AVAILITY => [
                'env' => config('services.availity.env', 'demo'),
                'app_id' => config('services.availity.app_id'),
                'token_url' => config('services.availity.token_url'),
                'api_base_url' => config('services.availity.api_base_url'),
                'scope_demo' => config('services.availity.scope_demo'),
                'scope_prod' => config('services.availity.scope_prod'),
                'request_type' => config('services.availity.request_type_code'),
                'default_payer_id' => config('services.availity.default_payer_id'),
                'default_diagnosis_code' => config('services.availity.default_diagnosis_code'),
                'place_of_service' => config('services.availity.place_of_service_code'),
                'patient_relationship' => config('services.availity.patient_relationship_code'),
                'token_cache_seconds' => config('services.availity.token_cache_seconds'),
                'timeout' => config('services.availity.timeout'),
                'mock_scenario_id' => config('services.availity.mock_scenario_id'),
            ],
            IntegrationCredential::KEY_ACCOUNTANTSWORLD => [
                'portal_url' => config('payroll.accountants_world_url'),
                'api_url' => config('payroll.accountants_world_api_url'),
                'auth_mode' => config('payroll.accountants_world_auth_mode', 'api_key'),
                'app_id' => config('payroll.accountants_world_app_id'),
                'oauth_token_url' => config('payroll.accountants_world_oauth_token_url'),
                'oauth_scope' => config('payroll.accountants_world_oauth_scope'),
                'oauth_client_id' => config('payroll.accountants_world_oauth_client_id'),
                'pay_schedule_id' => config('payroll.accountants_world_pay_schedule_id'),
                'default_pay_type_code' => config('payroll.accountants_world_default_pay_type_code'),
                'timeout' => config('payroll.accountants_world_timeout'),
                'accountant_email' => config('payroll.accountant_email'),
            ],
            IntegrationCredential::KEY_HHA => [
                'environment' => config('hha.environment', 'implementation'),
                'api_url' => config('hha.api_url'),
                'token_url' => config('hha.token_url'),
                'scope' => config('hha.scope', 'write:aggregator'),
                'attestation_status' => config('hha.attestation_status', 'pending'),
                'provider_tax_id' => config('hha.provider_tax_id'),
                'office_npi' => config('hha.office_npi'),
                'payer_id' => config('hha.payer_id'),
            ],
            IntegrationCredential::KEY_RINGCENTRAL => [
                'server_url' => config('ringcentral.server_url'),
                'extension' => config('ringcentral.extension'),
                'from_number' => config('ringcentral.from_number'),
                'timeout' => config('ringcentral.timeout'),
            ],
            IntegrationCredential::KEY_GOOGLE_WORKSPACE => [
                'delegated_user' => config('google_workspace.delegated_user'),
                'timeout' => config('google_workspace.timeout'),
            ],
            IntegrationCredential::KEY_SIGMA => [
                'portal_url' => config('billing_claims_audit.sigma_portal_url')
                    ?? config('global_settings.vault_rpa.'.IntegrationCredential::KEY_SIGMA.'.portal_url'),
                'default_asw_email' => config('billing_claims_audit.default_asw_email'),
            ],
            default => [],
        };
    }
}

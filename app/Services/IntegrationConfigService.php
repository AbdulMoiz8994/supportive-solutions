<?php

namespace App\Services;

use App\Models\IntegrationCredential;
use Illuminate\Support\Facades\Schema;

class IntegrationConfigService
{
    public function __construct(
        protected CredentialVaultService $vault,
        protected GlobalSettingsService $settings,
    ) {}

    public function hydrateRuntimeConfig(): void
    {
        if (! Schema::hasTable('integration_credentials')) {
            return;
        }

        $this->hydrateAvaility();
        $this->hydrateAccountantsWorld();
        $this->hydrateHha();
        $this->hydrateRingCentral();
        $this->hydrateGoogleWorkspace();
        $this->hydrateBillingClaims();
        $this->hydrateStatePortals();
        $this->hydrateDocuSign();
    }

    protected function hydrateAvaility(): void
    {
        $credential = $this->vault->get(IntegrationCredential::KEY_AVAILITY);
        $meta = $credential?->metadata ?? [];

        $overrides = $this->pick($meta, [
            'env',
            'app_id',
            'demo_key',
            'demo_secret',
            'prod_key',
            'prod_secret',
            'token_url',
            'api_base_url',
            'scope_demo',
            'scope_prod',
            'request_type',
            'default_payer_id',
            'default_diagnosis_code',
            'place_of_service',
            'patient_relationship',
            'mock_scenario_id',
        ], [
            'request_type' => 'request_type_code',
            'place_of_service' => 'place_of_service_code',
            'patient_relationship' => 'patient_relationship_code',
        ]);

        if (! empty($meta['token_cache_seconds'])) {
            $overrides['token_cache_seconds'] = (int) $meta['token_cache_seconds'];
        }

        if (! empty($meta['timeout'])) {
            $overrides['timeout'] = (int) $meta['timeout'];
        }

        if ($credential?->api_key && empty($overrides['demo_key'])) {
            $overrides['demo_key'] = $credential->api_key;
        }

        if ($credential?->password && empty($overrides['demo_secret'])) {
            $overrides['demo_secret'] = $credential->password;
        }

        if ($overrides !== []) {
            config(['services.availity' => array_merge(config('services.availity', []), $overrides)]);
        }
    }

    protected function hydrateAccountantsWorld(): void
    {
        $credential = $this->vault->get(IntegrationCredential::KEY_ACCOUNTANTSWORLD);
        $meta = $credential?->metadata ?? [];

        $payroll = [];

        if ($url = $this->stringOrNull($meta['portal_url'] ?? null)) {
            $payroll['accountants_world_url'] = $url;
        }

        if ($apiUrl = $this->stringOrNull($meta['api_url'] ?? null)) {
            $payroll['accountants_world_api_url'] = $apiUrl;
        }

        if ($appId = $this->stringOrNull($meta['app_id'] ?? null) ?? $this->stringOrNull($credential?->api_key)) {
            $payroll['accountants_world_app_id'] = $appId;
            $payroll['accountants_world_api_key'] = $appId;
        }

        if ($email = $this->stringOrNull($meta['accountant_email'] ?? null)) {
            $payroll['accountant_email'] = $email;
        }

        if (! empty($meta['timeout'])) {
            $payroll['accountants_world_timeout'] = (int) $meta['timeout'];
        }

        if ($payScheduleId = $this->stringOrNull($meta['pay_schedule_id'] ?? null)) {
            $payroll['accountants_world_pay_schedule_id'] = $payScheduleId;
        }

        if ($payTypeCode = $this->stringOrNull($meta['default_pay_type_code'] ?? null)) {
            $payroll['accountants_world_default_pay_type_code'] = $payTypeCode;
        }

        if ($oauthTokenUrl = $this->stringOrNull($meta['oauth_token_url'] ?? null)) {
            $payroll['accountants_world_oauth_token_url'] = $oauthTokenUrl;
        }

        if ($oauthScope = $this->stringOrNull($meta['oauth_scope'] ?? null)) {
            $payroll['accountants_world_oauth_scope'] = $oauthScope;
        }

        if ($oauthClientId = $this->stringOrNull($meta['oauth_client_id'] ?? null)) {
            $payroll['accountants_world_oauth_client_id'] = $oauthClientId;
        }

        if ($oauthSecret = $this->stringOrNull($meta['oauth_client_secret'] ?? null) ?? $this->stringOrNull($credential?->password)) {
            $payroll['accountants_world_oauth_client_secret'] = $oauthSecret;
        }

        if ($authMode = $this->stringOrNull($meta['auth_mode'] ?? null)) {
            $payroll['accountants_world_auth_mode'] = $authMode;
        }

        if ($payroll !== []) {
            config(['payroll' => array_merge(config('payroll', []), $payroll)]);
        }
    }

    protected function hydrateHha(): void
    {
        $credential = $this->vault->get(IntegrationCredential::KEY_HHA);
        $meta = $credential?->metadata ?? [];

        $hha = [];

        $environment = $this->stringOrNull($meta['environment'] ?? null);
        if ($environment) {
            $hha['environment'] = $environment;
            $base = config('hha.bases.'.$environment);
            if (is_string($base) && $base !== '') {
                if (! $this->stringOrNull($meta['api_url'] ?? null)) {
                    $hha['api_url'] = $base;
                }
                if (! $this->stringOrNull($meta['token_url'] ?? null)) {
                    $hha['token_url'] = $base.'/identity/connect/token';
                }
            }
        }

        if ($apiUrl = $this->stringOrNull($meta['api_url'] ?? null)) {
            $hha['api_url'] = app(\App\Services\HHA\HHAExchangeClient::class)->normalizeApiBaseUrl($apiUrl);
        }

        if ($tokenUrl = $this->stringOrNull($meta['token_url'] ?? null)) {
            $hha['token_url'] = $tokenUrl;
        }

        if ($scope = $this->stringOrNull($meta['scope'] ?? null)) {
            $hha['scope'] = $scope;
        }

        if ($status = $this->stringOrNull($meta['attestation_status'] ?? null)) {
            $hha['attestation_status'] = $status;
        }

        if ($providerTaxId = $this->stringOrNull($meta['provider_tax_id'] ?? null)) {
            $hha['provider_tax_id'] = preg_replace('/\D/', '', $providerTaxId) ?: $providerTaxId;
        }

        if ($officeNpi = $this->stringOrNull($meta['office_npi'] ?? null)) {
            $hha['office_npi'] = $officeNpi;
        }

        if ($payerId = $this->stringOrNull($meta['payer_id'] ?? null)) {
            $hha['payer_id'] = $payerId;
        }

        $clientId = $this->stringOrNull($meta['client_id'] ?? null) ?? $credential?->api_key;
        if ($clientId) {
            $hha['client_id'] = $clientId;
        }

        $clientSecret = $this->stringOrNull($meta['client_secret'] ?? null) ?? $credential?->password;
        if ($clientSecret) {
            $hha['client_secret'] = $clientSecret;
        }

        if ($hha !== []) {
            config(['hha' => array_merge(config('hha', []), $hha)]);
        }
    }

    protected function hydrateRingCentral(): void
    {
        $credential = $this->vault->get(IntegrationCredential::KEY_RINGCENTRAL);
        $meta = $credential?->metadata ?? [];

        $ringcentral = [];

        if ($serverUrl = $this->stringOrNull($meta['server_url'] ?? null)) {
            $ringcentral['server_url'] = $serverUrl;
        }

        if ($extension = $this->stringOrNull($meta['extension'] ?? null)) {
            $ringcentral['extension'] = $extension;
        }

        if ($fromNumber = $this->stringOrNull($meta['from_number'] ?? null)) {
            $ringcentral['from_number'] = $fromNumber;
        }

        if (! empty($meta['timeout'])) {
            $ringcentral['timeout'] = (int) $meta['timeout'];
        }

        $clientId = $this->stringOrNull($meta['client_id'] ?? null) ?? $credential?->api_key;
        if ($clientId) {
            $ringcentral['client_id'] = $clientId;
        }

        $clientSecret = $this->stringOrNull($meta['client_secret'] ?? null) ?? $credential?->password;
        if ($clientSecret) {
            $ringcentral['client_secret'] = $clientSecret;
        }

        $jwt = $this->stringOrNull($meta['jwt'] ?? null);
        if ($jwt) {
            $ringcentral['jwt'] = $jwt;
        }

        if ($ringcentral !== []) {
            config(['ringcentral' => array_merge(config('ringcentral', []), $ringcentral)]);
        }
    }

    protected function hydrateGoogleWorkspace(): void
    {
        $credential = $this->vault->get(IntegrationCredential::KEY_GOOGLE_WORKSPACE);
        $meta = $credential?->metadata ?? [];

        $google = [];

        $clientId = $this->stringOrNull($meta['client_id'] ?? null) ?? $credential?->api_key;
        if ($clientId) {
            $google['client_id'] = $clientId;
        }

        $clientSecret = $this->stringOrNull($meta['client_secret'] ?? null) ?? $credential?->password;
        if ($clientSecret) {
            $google['client_secret'] = $clientSecret;
        }

        if ($delegated = $this->stringOrNull($meta['delegated_user'] ?? null) ?? $credential?->username) {
            $google['delegated_user'] = $delegated;
        }

        $refreshToken = $this->stringOrNull($meta['refresh_token'] ?? null);
        if ($refreshToken) {
            $google['refresh_token'] = $refreshToken;
        }

        if (! empty($meta['timeout'])) {
            $google['timeout'] = (int) $meta['timeout'];
        }

        if ($google !== []) {
            config(['google_workspace' => array_merge(config('google_workspace', []), $google)]);
        }
    }

    protected function hydrateBillingClaims(): void
    {
        $billing = [];
        $sigmaMeta = $this->vault->get(IntegrationCredential::KEY_SIGMA)?->metadata ?? [];

        $aswEmail = $this->stringOrNull($this->settings->get('billing.default_asw_email'))
            ?? $this->stringOrNull($sigmaMeta['default_asw_email'] ?? null);

        if ($aswEmail) {
            $billing['default_asw_email'] = $aswEmail;
        }

        $portalUrl = $this->stringOrNull($this->settings->get('billing.sigma_portal_url'))
            ?? $this->stringOrNull($sigmaMeta['portal_url'] ?? null);

        if ($portalUrl) {
            $billing['sigma_portal_url'] = $portalUrl;
        }

        if ($billing !== []) {
            config(['billing_claims_audit' => array_merge(config('billing_claims_audit', []), $billing)]);
        }
    }

    protected function hydrateStatePortals(): void
    {
        $vaultRpa = config('global_settings.vault_rpa', []);

        foreach ([
            IntegrationCredential::KEY_CHAMPS,
            IntegrationCredential::KEY_SIGMA,
            IntegrationCredential::KEY_ICHAT,
            IntegrationCredential::KEY_MDHHS,
        ] as $key) {
            $meta = $this->vault->get($key)?->metadata ?? [];

            if ($portalUrl = $this->stringOrNull($meta['portal_url'] ?? null)) {
                $vaultRpa[$key] = array_merge($vaultRpa[$key] ?? [], ['portal_url' => $portalUrl]);
            }
        }

        config(['global_settings.vault_rpa' => $vaultRpa]);
    }

    protected function hydrateDocuSign(): void
    {
        $credential = $this->vault->get(IntegrationCredential::KEY_DOCUSIGN);
        $meta = $credential?->metadata ?? [];

        $overrides = array_filter([
            'integration_key' => $this->stringOrNull($credential?->api_key),
            'account_id' => $this->stringOrNull($credential?->username)
                ?? $this->stringOrNull($meta['account_id'] ?? null),
            'user_id' => $this->stringOrNull($meta['user_id'] ?? null)
                ?? $this->stringOrNull($meta['user_guid'] ?? null),
            'private_key' => $this->stringOrNull($credential?->password)
                ?? $this->stringOrNull($meta['private_key'] ?? null),
            'base_url' => $this->stringOrNull($meta['base_url'] ?? null),
            'oauth_host' => $this->stringOrNull($meta['oauth_host'] ?? null),
        ], fn ($value) => $value !== null && $value !== '');

        if (! empty($meta['timeout'])) {
            $overrides['timeout'] = (int) $meta['timeout'];
        }

        if ($overrides !== []) {
            config(['docusign' => array_merge(config('docusign', []), $overrides)]);
        }
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $keys
     * @param  array<string, string>  $rename
     * @return array<string, mixed>
     */
    protected function pick(array $source, array $keys, array $rename = []): array
    {
        $result = [];

        foreach ($keys as $key) {
            $value = $this->stringOrNull($source[$key] ?? null);

            if ($value === null) {
                continue;
            }

            $result[$rename[$key] ?? $key] = $value;
        }

        return $result;
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}

<?php

namespace App\Services;

use App\Models\IntegrationCredential;
use App\Services\Payroll\AccountantsWorldClient;
use App\Support\IntegrationCredentialFields;
use Illuminate\Support\Facades\Cache;

class CredentialVaultDraftService
{
    /**
     * @return array{username: string, password: string, api_key: string, metadata: array<string, mixed>}|null
     */
    public function normalize(?array $draft): ?array
    {
        if (! is_array($draft)) {
            return null;
        }

        $metadata = [];

        foreach ($draft['metadata'] ?? [] as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_string($value) || is_numeric($value)) {
                $trimmed = trim((string) $value);

                if ($trimmed !== '') {
                    $metadata[$key] = is_numeric($value) && ! in_array($key, ['token_cache_seconds', 'timeout'], true)
                        ? $value
                        : $trimmed;
                }
            }
        }

        return [
            'username' => trim((string) ($draft['username'] ?? '')),
            'password' => trim((string) ($draft['password'] ?? '')),
            'api_key' => trim((string) ($draft['api_key'] ?? '')),
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array{username: string, password: string, api_key: string, metadata: array<string, mixed>}  $draft
     */
    public function hasContent(array $draft): bool
    {
        return filled($draft['username'] ?? null)
            || filled($draft['password'] ?? null)
            || filled($draft['api_key'] ?? null)
            || ($draft['metadata'] ?? []) !== [];
    }

    /**
     * @param  array{username: string, password: string, api_key: string, metadata: array<string, mixed>}  $draft
     */
    public function isCompleteForTest(string $key, array $draft): bool
    {
        $meta = $draft['metadata'];

        return match ($key) {
            IntegrationCredential::KEY_AVAILITY => filled($meta['token_url'] ?? null)
                && filled($meta['api_base_url'] ?? null)
                && ((($meta['env'] ?? 'demo') === 'production')
                    ? filled($meta['prod_key'] ?? null) && filled($meta['prod_secret'] ?? null)
                    : filled($meta['demo_key'] ?? null) && filled($meta['demo_secret'] ?? null)),
            IntegrationCredential::KEY_ACCOUNTANTSWORLD => $this->accountantsWorldDraftComplete($meta),
            IntegrationCredential::KEY_HHA => filled($meta['api_url'] ?? null)
                && filled($meta['client_id'] ?? null)
                && filled($meta['client_secret'] ?? null)
                && filled($meta['scope'] ?? null),
            IntegrationCredential::KEY_RINGCENTRAL => filled($meta['server_url'] ?? null)
                && filled($meta['client_id'] ?? null)
                && filled($meta['client_secret'] ?? null)
                && filled($meta['jwt'] ?? null),
            IntegrationCredential::KEY_GOOGLE_WORKSPACE => filled($draft['username'])
                && filled($meta['client_id'] ?? null)
                && filled($meta['client_secret'] ?? null)
                && filled($meta['refresh_token'] ?? null),
            IntegrationCredential::KEY_SIGMA,
            IntegrationCredential::KEY_CHAMPS,
            IntegrationCredential::KEY_MDHHS,
            IntegrationCredential::KEY_ICHAT => filled($draft['username']) && filled($draft['password']),
            IntegrationCredential::KEY_DOCUSIGN => filled($draft['username']) && filled($draft['api_key']),
            default => false,
        };
    }

    /**
     * @param  array{username: string, password: string, api_key: string, metadata: array<string, mixed>}  $draft
     */
    public function apply(string $key, array $draft): callable
    {
        $meta = array_merge(IntegrationCredentialFields::defaults($key), $draft['metadata']);

        return match ($key) {
            IntegrationCredential::KEY_AVAILITY => $this->applyAvaility($meta),
            IntegrationCredential::KEY_ACCOUNTANTSWORLD => $this->applyAccountantsWorld($meta),
            IntegrationCredential::KEY_HHA => $this->applyHha($meta),
            IntegrationCredential::KEY_RINGCENTRAL => $this->applyRingCentral($meta),
            IntegrationCredential::KEY_GOOGLE_WORKSPACE => $this->applyGoogleWorkspace($draft, $meta),
            IntegrationCredential::KEY_DOCUSIGN => $this->applyDocuSign($draft, $meta),
            IntegrationCredential::KEY_SIGMA,
            IntegrationCredential::KEY_CHAMPS,
            IntegrationCredential::KEY_MDHHS,
            IntegrationCredential::KEY_ICHAT => $this->applyPortal($key, $meta),
            default => fn () => null,
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function applyAvaility(array $meta): callable
    {
        $original = config('services.availity', []);

        $overrides = array_filter([
            'env' => $meta['env'] ?? null,
            'app_id' => $meta['app_id'] ?? null,
            'demo_key' => $meta['demo_key'] ?? null,
            'demo_secret' => $meta['demo_secret'] ?? null,
            'prod_key' => $meta['prod_key'] ?? null,
            'prod_secret' => $meta['prod_secret'] ?? null,
            'token_url' => $meta['token_url'] ?? null,
            'api_base_url' => $meta['api_base_url'] ?? null,
            'scope_demo' => $meta['scope_demo'] ?? null,
            'scope_prod' => $meta['scope_prod'] ?? null,
            'request_type_code' => $meta['request_type'] ?? null,
            'default_payer_id' => $meta['default_payer_id'] ?? null,
            'default_diagnosis_code' => $meta['default_diagnosis_code'] ?? null,
            'place_of_service_code' => $meta['place_of_service'] ?? null,
            'patient_relationship_code' => $meta['patient_relationship'] ?? null,
            'mock_scenario_id' => $meta['mock_scenario_id'] ?? null,
        ], fn ($value) => filled($value));

        if (! empty($meta['token_cache_seconds'])) {
            $overrides['token_cache_seconds'] = (int) $meta['token_cache_seconds'];
        }

        if (! empty($meta['timeout'])) {
            $overrides['timeout'] = (int) $meta['timeout'];
        }

        config(['services.availity' => array_merge($original, $overrides)]);

        return fn () => config(['services.availity' => $original]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function applyAccountantsWorld(array $meta): callable
    {
        $original = config('payroll', []);
        $payroll = array_filter([
            'accountants_world_url' => $meta['portal_url'] ?? null,
            'accountants_world_api_url' => $meta['api_url'] ?? null,
            'accountants_world_auth_mode' => $meta['auth_mode'] ?? null,
            'accountants_world_app_id' => $meta['app_id'] ?? null,
            'accountants_world_api_key' => $meta['app_id'] ?? null,
            'accountants_world_oauth_client_id' => $meta['oauth_client_id'] ?? null,
            'accountants_world_oauth_client_secret' => $meta['oauth_client_secret'] ?? null,
            'accountants_world_oauth_token_url' => $meta['oauth_token_url'] ?? null,
            'accountants_world_oauth_scope' => $meta['oauth_scope'] ?? null,
            'accountant_email' => $meta['accountant_email'] ?? null,
        ], fn ($value) => filled($value));

        if (! empty($meta['timeout'])) {
            $payroll['accountants_world_timeout'] = (int) $meta['timeout'];
        }

        config(['payroll' => array_merge($original, $payroll)]);

        return fn () => config(['payroll' => $original]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function accountantsWorldDraftComplete(array $meta): bool
    {
        if (! filled($meta['api_url'] ?? null)) {
            return false;
        }

        $mode = $meta['auth_mode'] ?? AccountantsWorldClient::AUTH_MODE_API_KEY;

        return match ($mode) {
            AccountantsWorldClient::AUTH_MODE_OAUTH => filled($meta['oauth_client_id'] ?? null)
                && filled($meta['oauth_client_secret'] ?? null),
            AccountantsWorldClient::AUTH_MODE_BOTH => filled($meta['app_id'] ?? null)
                && filled($meta['oauth_client_id'] ?? null)
                && filled($meta['oauth_client_secret'] ?? null),
            default => filled($meta['app_id'] ?? null),
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function applyHha(array $meta): callable
    {
        $original = config('hha', []);
        $hha = array_filter([
            'environment' => $meta['environment'] ?? null,
            'api_url' => $meta['api_url'] ?? null,
            'token_url' => $meta['token_url'] ?? null,
            'scope' => $meta['scope'] ?? null,
            'attestation_status' => $meta['attestation_status'] ?? null,
            'client_id' => $meta['client_id'] ?? null,
            'client_secret' => $meta['client_secret'] ?? null,
            'provider_tax_id' => $meta['provider_tax_id'] ?? null,
            'office_npi' => $meta['office_npi'] ?? null,
            'payer_id' => $meta['payer_id'] ?? null,
        ], fn ($value) => filled($value));

        if (! empty($hha['api_url'])) {
            $hha['api_url'] = app(\App\Services\HHA\HHAExchangeClient::class)
                ->normalizeApiBaseUrl((string) $hha['api_url']);
        }

        if (! empty($hha['environment']) && empty($hha['api_url'])) {
            $base = config('hha.bases.'.$hha['environment']);
            if (is_string($base) && $base !== '') {
                $hha['api_url'] = $base;
                $hha['token_url'] = $hha['token_url'] ?? ($base.'/identity/connect/token');
            }
        }

        if (! empty($hha['provider_tax_id'])) {
            $hha['provider_tax_id'] = preg_replace('/\D/', '', (string) $hha['provider_tax_id']) ?: $hha['provider_tax_id'];
        }

        config(['hha' => array_merge($original, $hha)]);

        return fn () => config(['hha' => $original]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function applyRingCentral(array $meta): callable
    {
        $original = config('ringcentral', []);
        $ringcentral = array_filter([
            'server_url' => $meta['server_url'] ?? null,
            'extension' => $meta['extension'] ?? null,
            'from_number' => $meta['from_number'] ?? null,
            'client_id' => $meta['client_id'] ?? null,
            'client_secret' => $meta['client_secret'] ?? null,
            'jwt' => $meta['jwt'] ?? null,
        ], fn ($value) => filled($value));

        if (! empty($meta['timeout'])) {
            $ringcentral['timeout'] = (int) $meta['timeout'];
        }

        config(['ringcentral' => array_merge($original, $ringcentral)]);

        $clientId = (string) ($ringcentral['client_id'] ?? '');

        if ($clientId !== '') {
            Cache::forget('ringcentral.access_token.'.md5($clientId));
            Cache::forget('ringcentral.extension_from_number.'.md5($clientId));
        }

        return fn () => config(['ringcentral' => $original]);
    }

    /**
     * @param  array{username: string, password: string, api_key: string, metadata: array<string, mixed>}  $draft
     * @param  array<string, mixed>  $meta
     */
    protected function applyGoogleWorkspace(array $draft, array $meta): callable
    {
        $original = config('google_workspace', []);
        $google = array_filter([
            'client_id' => $meta['client_id'] ?? null,
            'client_secret' => $meta['client_secret'] ?? null,
            'delegated_user' => $draft['username'] ?: null,
            'refresh_token' => $meta['refresh_token'] ?? null,
        ], fn ($value) => filled($value));

        if (! empty($meta['timeout'])) {
            $google['timeout'] = (int) $meta['timeout'];
        }

        config(['google_workspace' => array_merge($original, $google)]);

        $clientId = (string) ($google['client_id'] ?? '');

        if ($clientId !== '') {
            Cache::forget('google_workspace.access_token.'.md5($clientId));
        }

        return fn () => config(['google_workspace' => $original]);
    }

    /**
     * @param  array{username: string, password: string, api_key: string, metadata: array<string, mixed>}  $draft
     * @param  array<string, mixed>  $meta
     */
    protected function applyDocuSign(array $draft, array $meta): callable
    {
        $original = config('docusign', []);
        $docusign = array_filter([
            'integration_key' => $draft['api_key'] ?: null,
            'account_id' => $draft['username'] ?: ($meta['account_id'] ?? null),
            'base_url' => $meta['base_url'] ?? null,
        ], fn ($value) => filled($value));

        if (! empty($meta['timeout'])) {
            $docusign['timeout'] = (int) $meta['timeout'];
        }

        config(['docusign' => array_merge($original, $docusign)]);

        return fn () => config(['docusign' => $original]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function applyPortal(string $key, array $meta): callable
    {
        $originalVaultRpa = config('global_settings.vault_rpa', []);

        if (filled($meta['portal_url'] ?? null)) {
            $vaultRpa = $originalVaultRpa;
            $vaultRpa[$key] = array_merge($vaultRpa[$key] ?? [], ['portal_url' => $meta['portal_url']]);
            config(['global_settings.vault_rpa' => $vaultRpa]);
        }

        return function () use ($originalVaultRpa): void {
            config(['global_settings.vault_rpa' => $originalVaultRpa]);
        };
    }
}

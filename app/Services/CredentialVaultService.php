<?php

namespace App\Services;

use App\Models\IntegrationCredential;
use App\Support\IntegrationCredentialFields;

class CredentialVaultService
{
    /**
     * @return array<string, array{label: string, configured: bool, username: ?string, has_password: bool, has_api_key: bool, metadata: array<string, mixed>, secret_flags: array<string, bool>}>
     */
    public function summaryForView(): array
    {
        $stored = IntegrationCredential::query()
            ->whereIn('key', array_keys(IntegrationCredential::supportedKeys()))
            ->get()
            ->keyBy('key');

        $summary = [];

        foreach (IntegrationCredential::supportedKeys() as $key => $label) {
            $credential = $stored->get($key);
            $metadata = array_merge(
                IntegrationCredentialFields::defaults($key),
                $credential?->metadata ?? []
            );
            $metadata = $this->normalizeMetadataForView($key, $metadata, $credential);
            $secretFlags = [];

            foreach (IntegrationCredentialFields::secretMetadataKeys($key) as $secretKey) {
                $secretFlags[$secretKey] = ! empty($metadata[$secretKey] ?? null);
            }

            if ($key === IntegrationCredential::KEY_AVAILITY) {
                $secretFlags['demo_key'] = $secretFlags['demo_key'] || (bool) $credential?->api_key;
                $secretFlags['demo_secret'] = $secretFlags['demo_secret'] || (bool) $credential?->password;
            }

            if ($key === IntegrationCredential::KEY_ACCOUNTANTSWORLD) {
                $secretFlags['api_key'] = filled($metadata['app_id'] ?? null) || (bool) $credential?->api_key;
            }

            if ($key === IntegrationCredential::KEY_HHA) {
                $secretFlags['client_id'] = filled($metadata['client_id'] ?? null) || (bool) $credential?->api_key;
                $secretFlags['client_secret'] = filled($metadata['client_secret'] ?? null) || (bool) $credential?->password;
            }

            if ($key === IntegrationCredential::KEY_RINGCENTRAL) {
                $secretFlags['client_id'] = filled($metadata['client_id'] ?? null) || (bool) $credential?->api_key;
                $secretFlags['client_secret'] = filled($metadata['client_secret'] ?? null) || (bool) $credential?->password;
            }

            if ($key === IntegrationCredential::KEY_GOOGLE_WORKSPACE) {
                $secretFlags['client_id'] = filled($metadata['client_id'] ?? null);
                $secretFlags['client_secret'] = filled($metadata['client_secret'] ?? null);
                $secretFlags['refresh_token'] = filled($metadata['refresh_token'] ?? null);
            }

            $configured = $credential !== null && (
                $credential->username
                || $credential->password
                || $credential->api_key
                || $this->hasIntegrationMetadata($key, $metadata, $secretFlags)
            );

            if ($key === IntegrationCredential::KEY_GOOGLE_WORKSPACE) {
                $configured = ($secretFlags['client_id'] ?? false)
                    && ($secretFlags['client_secret'] ?? false)
                    && ($secretFlags['refresh_token'] ?? false)
                    && filled($credential?->username);
            }

            $summary[$key] = [
                'label' => $label,
                'configured' => $configured,
                'username' => $credential?->username,
                'has_password' => (bool) ($credential?->password),
                'has_api_key' => (bool) ($credential?->api_key),
                'metadata' => $this->metadataForView($key, $metadata, $secretFlags),
                'secret_flags' => $secretFlags,
            ];
        }

        return $summary;
    }

    public function get(string $key): ?IntegrationCredential
    {
        return IntegrationCredential::where('key', $key)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(string $key): array
    {
        $credential = $this->get($key);

        return array_merge(
            IntegrationCredentialFields::defaults($key),
            $credential?->metadata ?? []
        );
    }

    public function username(string $key): ?string
    {
        return $this->get($key)?->username;
    }

    public function password(string $key): ?string
    {
        return $this->get($key)?->password;
    }

    public function apiKey(string $key): ?string
    {
        return $this->get($key)?->api_key;
    }

    /**
     * @param  array{username?: ?string, password?: ?string, api_key?: ?string, metadata?: array<string, mixed>}  $data
     */
    public function upsert(string $key, array $data): IntegrationCredential
    {
        $credential = IntegrationCredential::firstOrNew(['key' => $key]);

        if (array_key_exists('username', $data)) {
            $credential->username = $data['username'] ?: null;
        }

        if (! empty($data['password'])) {
            $credential->password = $data['password'];
        }

        if (! empty($data['api_key'])) {
            $credential->api_key = $data['api_key'];
        }

        if (array_key_exists('metadata', $data) && is_array($data['metadata'])) {
            $existing = $credential->metadata ?? [];
            $incoming = [];

            foreach ($data['metadata'] as $metaKey => $value) {
                if ($value === null || $value === '') {
                    if (in_array($metaKey, IntegrationCredentialFields::secretMetadataKeys($key), true)) {
                        continue;
                    }

                    continue;
                }

                $incoming[$metaKey] = is_numeric($value) && in_array($metaKey, ['token_cache_seconds', 'timeout'], true)
                    ? (int) $value
                    : $value;
            }

            $credential->metadata = array_merge($existing, $incoming);
        }

        if (in_array($key, [IntegrationCredential::KEY_RINGCENTRAL, IntegrationCredential::KEY_HHA], true)) {
            $meta = $credential->metadata ?? [];

            if ($key === IntegrationCredential::KEY_HHA && ! empty($meta['api_url'])) {
                $meta['api_url'] = app(\App\Services\HHA\HHAExchangeClient::class)
                    ->normalizeApiBaseUrl((string) $meta['api_url']);
                $credential->metadata = $meta;
            }

            if (! empty($meta['client_id'])) {
                $credential->api_key = $meta['client_id'];
            }

            if (! empty($meta['client_secret'])) {
                $credential->password = $meta['client_secret'];
            }
        }

        if ($key === IntegrationCredential::KEY_ACCOUNTANTSWORLD) {
            $meta = $credential->metadata ?? [];
            $appId = trim((string) ($meta['app_id'] ?? ''));

            if ($appId !== '') {
                $credential->api_key = $appId;
            }
        }

        $credential->save();

        return $credential;
    }

    /**
     * CHAMPS credentials should be stored in the vault; Employee.champs_password is deprecated.
     */
    public function champsConfigured(): bool
    {
        $credential = $this->get(IntegrationCredential::KEY_CHAMPS);

        return $credential !== null && ($credential->username || $credential->password);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, bool>  $secretFlags
     */
    protected function metadataForView(string $key, array $metadata, array $secretFlags): array
    {
        $view = $metadata;

        foreach (IntegrationCredentialFields::secretMetadataKeys($key) as $secretKey) {
            if ($secretFlags[$secretKey] ?? false) {
                unset($view[$secretKey]);
            }
        }

        return $view;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function normalizeMetadataForView(string $key, array $metadata, ?IntegrationCredential $credential): array
    {
        if (in_array($key, [IntegrationCredential::KEY_RINGCENTRAL, IntegrationCredential::KEY_HHA], true)) {
            if (empty($metadata['client_id']) && $credential?->api_key) {
                $metadata['client_id'] = $credential->api_key;
            }

            if (empty($metadata['client_secret']) && $credential?->password) {
                $metadata['client_secret'] = $credential->password;
            }
        }

        if ($key === IntegrationCredential::KEY_ACCOUNTANTSWORLD) {
            if (empty($metadata['app_id']) && $credential?->api_key) {
                $metadata['app_id'] = $credential->api_key;
            }
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, bool>  $secretFlags
     */
    protected function hasIntegrationMetadata(string $key, array $metadata, array $secretFlags): bool
    {
        if ($key === IntegrationCredential::KEY_AVAILITY) {
            return ($secretFlags['demo_key'] ?? false)
                || ! empty($metadata['default_payer_id'])
                || ! empty($metadata['env']);
        }

        if ($key === IntegrationCredential::KEY_ACCOUNTANTSWORLD) {
            return ($secretFlags['api_key'] ?? false)
                || ! empty($metadata['app_id'])
                || ! empty($metadata['accountant_email']);
        }

        if ($key === IntegrationCredential::KEY_HHA) {
            return ($secretFlags['client_id'] ?? false)
                && ($secretFlags['client_secret'] ?? false)
                && ! empty($metadata['api_url']);
        }

        if ($key === IntegrationCredential::KEY_RINGCENTRAL) {
            return ($secretFlags['client_id'] ?? false)
                && filled($metadata['jwt'] ?? null);
        }

        if ($key === IntegrationCredential::KEY_GOOGLE_WORKSPACE) {
            return ($secretFlags['client_id'] ?? false)
                && ($secretFlags['client_secret'] ?? false)
                && ($secretFlags['refresh_token'] ?? false);
        }

        if ($key === IntegrationCredential::KEY_DOCUSIGN) {
            return ($secretFlags['api_key'] ?? false);
        }

        return false;
    }
}

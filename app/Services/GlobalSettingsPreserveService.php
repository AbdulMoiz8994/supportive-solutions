<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class GlobalSettingsPreserveService
{
    public const BACKUP_PATH = 'private/global-settings-preserve.json';

    /** @deprecated Use GlobalSettingsPreserveService::BACKUP_PATH */
    public const LEGACY_CREDENTIAL_VAULT_BACKUP_PATH = 'private/credential-vault-preserve.json';

    /**
     * @var list<string>
     */
    protected const ORGANIZATION_COLUMNS = [
        'name',
        'address',
        'contact_info',
        'status',
        'agency_npi',
        'tax_id_ein',
        'medicaid_provider_id',
        'legal_business_name',
        'legal_address_street',
        'legal_address_city',
        'legal_address_state',
        'legal_address_zip',
        'main_phone',
        'efax_number',
        'service_state',
    ];

    public function backup(): void
    {
        $payload = [
            'backed_up_at' => now()->toIso8601String(),
            'settings' => $this->backupSettings(),
            'integration_credentials' => $this->backupIntegrationCredentials(),
            'global_integration_health' => $this->backupGlobalIntegrationHealth(),
            'organization' => $this->backupPrimaryOrganization(),
        ];

        Storage::disk('local')->put(
            self::BACKUP_PATH,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    public function restore(): void
    {
        $payload = $this->readBackupPayload();

        if ($payload === null) {
            return;
        }

        $this->restoreSettings($payload['settings'] ?? []);
        $this->restoreIntegrationCredentials($payload['integration_credentials'] ?? []);
        $this->restoreGlobalIntegrationHealth($payload['global_integration_health'] ?? []);
        $this->restorePrimaryOrganization($payload['organization'] ?? null);

        app(IntegrationConfigService::class)->hydrateRuntimeConfig();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function backupSettings(): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        return DB::table('settings')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'key' => $row->key,
                'value_payload' => $row->value_payload,
                'group' => $row->group,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function backupIntegrationCredentials(): array
    {
        if (! Schema::hasTable('integration_credentials')) {
            return [];
        }

        return DB::table('integration_credentials')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'key' => $row->key,
                'username' => $row->username,
                'password' => $row->password,
                'api_key' => $row->api_key,
                'metadata' => $row->metadata,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function backupGlobalIntegrationHealth(): array
    {
        if (! Schema::hasTable('global_integration_health')) {
            return [];
        }

        return DB::table('global_integration_health')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'slug' => $row->slug,
                'status' => $row->status,
                'message' => $row->message,
                'latency_ms' => $row->latency_ms,
                'details' => $row->details,
                'last_tested_at' => $row->last_tested_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function backupPrimaryOrganization(): ?array
    {
        if (! Schema::hasTable('organizations')) {
            return null;
        }

        $organization = Organization::query()->orderBy('id')->first();

        if ($organization === null) {
            return null;
        }

        $snapshot = [];

        foreach (self::ORGANIZATION_COLUMNS as $column) {
            $snapshot[$column] = $organization->{$column};
        }

        return $snapshot;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function restoreSettings(array $rows): void
    {
        if ($rows === [] || ! Schema::hasTable('settings')) {
            return;
        }

        foreach ($rows as $row) {
            DB::table('settings')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'value_payload' => $row['value_payload'],
                    'group' => $row['group'] ?? 'general',
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ]
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function restoreIntegrationCredentials(array $rows): void
    {
        if ($rows === [] || ! Schema::hasTable('integration_credentials')) {
            return;
        }

        foreach ($rows as $row) {
            DB::table('integration_credentials')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'username' => $row['username'],
                    'password' => $row['password'],
                    'api_key' => $row['api_key'],
                    'metadata' => $row['metadata'],
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ]
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function restoreGlobalIntegrationHealth(array $rows): void
    {
        if ($rows === [] || ! Schema::hasTable('global_integration_health')) {
            return;
        }

        foreach ($rows as $row) {
            DB::table('global_integration_health')->updateOrInsert(
                ['slug' => $row['slug']],
                [
                    'status' => $row['status'],
                    'message' => $row['message'],
                    'latency_ms' => $row['latency_ms'],
                    'details' => $row['details'],
                    'last_tested_at' => $row['last_tested_at'],
                    'last_tested_by' => null,
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    protected function restorePrimaryOrganization(?array $snapshot): void
    {
        if ($snapshot === null || ! Schema::hasTable('organizations')) {
            return;
        }

        $organization = Organization::query()->orderBy('id')->first();

        if ($organization === null) {
            return;
        }

        $attributes = [];

        foreach (self::ORGANIZATION_COLUMNS as $column) {
            if (array_key_exists($column, $snapshot)) {
                $attributes[$column] = $snapshot[$column];
            }
        }

        if ($attributes !== []) {
            $organization->update($attributes);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readBackupPayload(): ?array
    {
        if (Storage::disk('local')->exists(self::BACKUP_PATH)) {
            return json_decode(Storage::disk('local')->get(self::BACKUP_PATH), true, 512, JSON_THROW_ON_ERROR);
        }

        if (! Storage::disk('local')->exists(self::LEGACY_CREDENTIAL_VAULT_BACKUP_PATH)) {
            return null;
        }

        $legacy = json_decode(
            Storage::disk('local')->get(self::LEGACY_CREDENTIAL_VAULT_BACKUP_PATH),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return [
            'settings' => [],
            'integration_credentials' => $legacy['rows'] ?? [],
            'global_integration_health' => [],
            'organization' => null,
        ];
    }
}

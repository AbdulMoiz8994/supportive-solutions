<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\IntegrationCredential;

class DirectoryIntegrationCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function vendors(): array
    {
        return config('directory_integrations.vendors', []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function systems(): array
    {
        return config('directory_integrations.systems', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function vendor(?string $slug): ?array
    {
        if (! $slug) {
            return null;
        }

        return self::vendors()[$slug] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forContact(Contact $contact): ?array
    {
        if ($contact->type === Contact::TYPE_VENDOR && filled($contact->integration_slug)) {
            return self::vendor($contact->integration_slug);
        }

        return null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function vendorOptions(): array
    {
        return collect(self::vendors())
            ->map(fn (array $entry, string $slug) => [
                'value' => $slug,
                'label' => $entry['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function credentialKeyOptions(): array
    {
        return collect(IntegrationCredential::supportedKeys())
            ->map(fn (string $label, string $key) => [
                'value' => $key,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    /**
     * Apply catalog defaults when a vendor slug is selected.
     *
     * @return array<string, mixed>
     */
    public static function defaultsForSlug(string $slug): array
    {
        $entry = self::vendor($slug);

        if (! $entry) {
            return [];
        }

        return [
            'integration_slug' => $slug,
            'integration_credential_key' => $entry['credential_key'] ?? null,
            'data_flow' => $entry['data_flow'] ?? null,
            'app_area' => $entry['app_area'] ?? null,
            'owning_agent' => $entry['owning_agent'] ?? null,
        ];
    }

    public static function appRouteForContact(Contact $contact): ?string
    {
        $catalog = self::forContact($contact);
        $routeName = $catalog['app_route'] ?? null;

        if ($routeName && \Illuminate\Support\Facades\Route::has($routeName)) {
            return $routeName;
        }

        return match ($contact->app_area) {
            'payroll' => 'payroll',
            'communications' => 'communications.index',
            'billing' => 'billing-claims-audit.index',
            'calendar' => 'calendar',
            'compliance' => 'compliance.index',
            'intake' => 'clients.index',
            default => null,
        };
    }

    public static function appLabelForContact(Contact $contact): ?string
    {
        $catalog = self::forContact($contact);

        if ($catalog['app_label'] ?? null) {
            return $catalog['app_label'];
        }

        return match ($contact->app_area) {
            'payroll' => 'Payroll tab',
            'communications' => 'Communications tab',
            'billing' => 'Billing & Claims',
            'calendar' => 'Calendar / Visit Reports',
            'compliance' => 'Compliance Forms',
            'intake' => 'Client → Eligibility',
            default => null,
        };
    }

    public static function credentialVaultUrl(?string $credentialKey = null): string
    {
        $params = ['tab' => 'credential-vault'];

        if ($credentialKey) {
            $params['integration'] = $credentialKey;
        }

        return route('settings.global', $params);
    }
}

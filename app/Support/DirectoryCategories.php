<?php

namespace App\Support;

use App\Models\Contact;

class DirectoryCategories
{
    /**
     * Client design — 8 directory groupings (Tab 12 · Directories).
     *
     * @return list<array{key: string, label: string, tab_label: string, hint: string, types: list<string>, icon: string, card_icon_bg: string, card_active_border: string, show_layout?: string, detail_label?: string, panel_subtitle?: string}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'payers',
                'label' => 'Payers / MCOs',
                'tab_label' => 'Payers / MCOs',
                'detail_label' => 'Payers / MCOs',
                'hint' => 'plans',
                'types' => [Contact::TYPE_INSURANCE],
                'icon' => 'payers',
                'card_icon_bg' => 'bg-[#dbeafe]',
                'card_active_border' => 'border-[#2563eb] shadow-[0_0_0_2px_#dbeafe]',
                'show_layout' => 'payer',
                'panel_subtitle' => 'Claim channel, provider line, and linked clients for each plan',
            ],
            [
                'key' => 'asws',
                'label' => 'DHS — ASWs',
                'tab_label' => 'DHS ASWs',
                'detail_label' => 'DHS — ASWs',
                'hint' => 'workers',
                'types' => [Contact::TYPE_AGENCY_STAFF],
                'icon' => 'asws',
                'card_icon_bg' => 'bg-[#ede9fe]',
                'card_active_border' => 'border-[#2563eb] shadow-[0_0_0_2px_#dbeafe]',
                'show_layout' => 'asw',
                'panel_subtitle' => 'Adult Services Workers, coverage area, and linked DHS clients',
            ],
            [
                'key' => 'coordinators',
                'label' => 'MICH — Case Coordinators',
                'tab_label' => 'Case Coordinators',
                'detail_label' => 'MICH — Case Coordinators',
                'hint' => 'contacts',
                'types' => [Contact::TYPE_CASE_COORDINATOR],
                'icon' => 'coordinators',
                'card_icon_bg' => 'bg-[#e0f2fe]',
                'card_active_border' => 'border-[#2563eb] shadow-[0_0_0_2px_#dbeafe]',
                'show_layout' => 'coordinator',
                'panel_subtitle' => 'PA contacts by plan with direct lines and linked members',
            ],
            [
                'key' => 'physicians',
                'label' => 'Physicians / PCPs',
                'tab_label' => 'Physicians',
                'detail_label' => 'Physicians / PCPs',
                'hint' => 'providers',
                'types' => [Contact::TYPE_PCP],
                'icon' => 'physicians',
                'card_icon_bg' => 'bg-[#d1fae5]',
                'card_active_border' => 'border-[#2563eb] shadow-[0_0_0_2px_#dbeafe]',
                'show_layout' => 'physician',
                'panel_subtitle' => 'NPI, practice, and clients under each provider',
            ],
            [
                'key' => 'referrals',
                'label' => 'Referral sources',
                'tab_label' => 'Referral sources',
                'detail_label' => 'Referral sources',
                'hint' => 'sources',
                'types' => [Contact::TYPE_REFERRAL, Contact::TYPE_FAMILY_EMERGENCY],
                'icon' => 'referrals',
                'card_icon_bg' => 'bg-[#fef3c7]',
                'card_active_border' => 'border-[#2563eb] shadow-[0_0_0_2px_#dbeafe]',
                'show_layout' => 'referral',
                'panel_subtitle' => 'Inbound referral partners and conversion tracking',
            ],
            [
                'key' => 'state_systems',
                'label' => 'State systems & portals',
                'tab_label' => 'State systems',
                'detail_label' => 'State systems & portals',
                'hint' => 'systems',
                'types' => [Contact::TYPE_OTHER],
                'icon' => 'systems',
                'card_icon_bg' => 'bg-[#f1f5f9]',
                'card_active_border' => 'border-[#2563eb] shadow-[0_0_0_2px_#dbeafe]',
                'show_layout' => 'system',
                'panel_subtitle' => 'RPA portals, purpose, and credential references',
            ],
            [
                'key' => 'vendors',
                'label' => 'Vendors / integrations',
                'tab_label' => 'Vendors',
                'detail_label' => 'Vendors / integrations',
                'hint' => 'vendors',
                'types' => [Contact::TYPE_VENDOR],
                'icon' => 'vendors',
                'card_icon_bg' => 'bg-[#fce7f3]',
                'card_active_border' => 'border-[#2563eb] shadow-[0_0_0_2px_#dbeafe]',
                'show_layout' => 'integration',
                'panel_subtitle' => 'API integrations, sync health, and support contacts',
            ],
            [
                'key' => 'pharmacies',
                'label' => 'Pharmacies / facilities',
                'tab_label' => 'Pharmacies / facilities',
                'detail_label' => 'Pharmacies / facilities',
                'hint' => 'entries',
                'types' => [Contact::TYPE_PHARMACY],
                'icon' => 'pharmacies',
                'card_icon_bg' => 'bg-[#e0f2fe]',
                'card_active_border' => 'border-[#2563eb] shadow-[0_0_0_2px_#dbeafe]',
                'show_layout' => 'pharmacy',
                'panel_subtitle' => 'Reference pharmacies and facilities used by clients',
            ],
        ];
    }

    /**
     * Legacy category keys from earlier builds.
     *
     * @var array<string, string>
     */
    private const LEGACY_KEYS = [
        'insurance' => 'payers',
    ];

    public static function layoutForType(?string $type): string
    {
        if ($type === Contact::TYPE_VENDOR) {
            return 'integration';
        }

        $category = self::forType($type);

        return $category['show_layout'] ?? 'contact';
    }

    /**
     * @return list<string>
     */
    public static function typesFor(?string $key): array
    {
        if (! $key) {
            return [];
        }

        $key = self::LEGACY_KEYS[$key] ?? $key;

        foreach (self::all() as $category) {
            if ($category['key'] === $key) {
                return $category['types'];
            }
        }

        return [];
    }

    public static function forType(?string $type): ?array
    {
        if (! $type) {
            return null;
        }

        foreach (self::all() as $category) {
            if (in_array($type, $category['types'], true)) {
                return $category;
            }
        }

        return null;
    }

    public static function forKey(?string $key): ?array
    {
        if (! $key) {
            return null;
        }

        $key = self::LEGACY_KEYS[$key] ?? $key;

        foreach (self::all() as $category) {
            if ($category['key'] === $key) {
                return $category;
            }
        }

        return null;
    }

    public static function iconSvg(string $icon): string
    {
        $paths = [
            'payers' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M7 7V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"/>',
            'asws' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/><path d="M8 7h6"/><path d="M8 11h8"/>',
            'coordinators' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'physicians' => '<path d="M11 2v2"/><path d="M5 2v2"/><path d="M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1"/><path d="M8 15a6 6 0 0 0 12 0v-3"/><circle cx="20" cy="10" r="2"/>',
            'pharmacies' => '<path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/>',
            'referrals' => '<path d="M16 3h5v5"/><path d="M8 3H3v5"/><path d="M12 22v-8.3a4 4 0 0 0-1.172-2.872L3 3"/><path d="m15 9 6-6"/>',
            'systems' => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>',
            'vendors' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'other' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
        ];

        $path = $paths[$icon] ?? $paths['other'];

        return '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$path.'</svg>';
    }
}

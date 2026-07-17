<?php

namespace App\Support;

class TabbedPageTitle
{
    /** @var array<string, string> */
    private const GLOBAL_SETTINGS_LEGACY_MAP = [
        'agency' => 'agency-profile',
        'security' => 'security-compliance',
        'retention' => 'security-compliance',
        'billing' => 'programs-rates',
        'flags' => 'security-compliance',
        'workflows' => 'agency-profile',
        'templates' => 'notifications-language',
        'notifications' => 'notifications-language',
        'compliance' => 'security-compliance',
    ];

    /** @var array<string, string> */
    private const GLOBAL_SETTINGS_TAB_LABELS = [
        'agency-profile' => 'Agency Profile',
        'programs-rates' => 'Programs & Rates',
        'integrations' => 'Integrations & Connections',
        'billing-claims' => 'Billing & Claims',
        'credential-vault' => 'Credential Vault',
        'security-compliance' => 'Security & Compliance',
        'access-activation' => 'Access & Activation',
        'ai-automation' => 'AI & Automation',
        'notifications-language' => 'Notifications & Language',
    ];

    /** @var array<string, string> */
    public const CLIENT_TAB_LABELS = [
        'demographics' => 'Demographics & Eligibility',
        'intake' => 'Intake & Screening',
        'authorization' => 'Program & Authorization',
        'caregiver' => 'Caregiver Assignment',
        'compliance' => 'Compliance Forms',
        'billing' => 'Billing History',
        'documents' => 'Documents',
        'communications' => 'Communications',
        'schedule' => 'Visits / Schedule',
        'notes' => 'Notes & Activity',
        'audit' => 'Audit Trail',
    ];

    /** @var array<string, string> */
    public const STAFF_TAB_LABELS = [
        'profile' => 'Profile',
        'permission' => 'Permission',
        'activity' => 'Activity Log',
    ];

    /** @var array<string, string> */
    public const CAREGIVER_TAB_LABELS = [
        'personal' => 'Personal & Employment',
        'onboarding' => 'Application & Onboarding',
        'checks' => 'Background Checks',
        'assignments' => 'Client Assignments',
        'schedule' => 'Schedule / Visits',
        'access' => 'Apps & Access',
        'compliance' => 'Compliance Forms',
        'pay' => 'Pay & Payroll',
        'documents' => 'Documents',
        'communications' => 'Communications',
        'notes' => 'Notes & Activity',
        'audit' => 'Audit Trail',
    ];

    public static function tabbed(string $tabLabel, string $context): string
    {
        return $tabLabel.' — '.$context;
    }

    public static function globalSettings(?string $tab = null): string
    {
        $tab = $tab ?: 'agency-profile';
        $resolved = self::GLOBAL_SETTINGS_LEGACY_MAP[$tab] ?? $tab;
        $label = self::GLOBAL_SETTINGS_TAB_LABELS[$resolved] ?? 'Agency Profile';

        return self::tabbed($label, 'Global Settings');
    }

    public static function client(string $clientName, ?string $tab = null): string
    {
        $tab = $tab ?: 'demographics';
        $label = self::CLIENT_TAB_LABELS[$tab] ?? self::CLIENT_TAB_LABELS['demographics'];

        return self::tabbed($label, trim($clientName) ?: 'Client Details');
    }

    public static function caregiver(string $caregiverName, ?string $tab = null): string
    {
        $tab = $tab ?: 'personal';
        $label = self::CAREGIVER_TAB_LABELS[$tab] ?? self::CAREGIVER_TAB_LABELS['personal'];

        return self::tabbed($label, trim($caregiverName) ?: 'Caregiver Details');
    }

    public static function staff(string $staffName, ?string $tab = null): string
    {
        $tab = $tab ?: 'profile';
        $label = self::STAFF_TAB_LABELS[$tab] ?? self::STAFF_TAB_LABELS['profile'];

        return self::tabbed($label, trim($staffName) ?: 'Staff Member');
    }

    public static function staffAiAgents(string $tab = 'agents'): string
    {
        return match ($tab) {
            'operations' => self::tabbed('AI Operations', 'Staff & AI Agents'),
            'staff' => self::tabbed('Staff', 'Staff & AI Agents'),
            default => 'Staff & AI Agents',
        };
    }

    /** @return array<string, string> */
    public static function globalSettingsTabLabels(): array
    {
        return self::GLOBAL_SETTINGS_TAB_LABELS;
    }
}

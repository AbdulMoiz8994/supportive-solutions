<?php

namespace App\Support;

use App\Models\Contact;
use App\Support\DirectoryIntegrationCatalog;
use Illuminate\Support\Str;

class DirectoryShowLayout
{
    /**
     * @return array{
     *     layout: string,
     *     breadcrumb_label: string,
     *     badges: list<array{label: string, variant: string}>,
     *     subtitle: string,
     *     profile_subtext: string,
     *     actions: list<array{type: string, label: string, href?: string, icon: string, disabled?: bool}>,
     *     main_sections: list<array{title: string, rows: list<array{label: string, value: ?string, href?: ?string, html?: bool}>}>,
     *     glance_title: string,
     *     glance_rows: list<array{label: string, value: string, badge_variant?: ?string}>,
     *     related_links: list<array{label: string, href: ?string, suffix: string, icon: string, muted?: ?string}>,
     *     show_design_note: bool,
     * }
     */
    public static function forContact(Contact $contact): array
    {
        $layout = self::layoutFor($contact);
        $category = DirectoryCategories::forType($contact->type);
        $paragraphs = self::noteParagraphs($contact->notes);

        return match ($layout) {
            'payer' => self::payerLayout($contact, $category, $paragraphs),
            'asw' => self::aswLayout($contact, $category, $paragraphs),
            'coordinator' => self::coordinatorLayout($contact, $category, $paragraphs),
            'physician' => self::physicianLayout($contact, $category, $paragraphs),
            'referral' => self::referralLayout($contact, $category, $paragraphs),
            'pharmacy' => self::pharmacyLayout($contact, $category, $paragraphs),
            'integration' => self::integrationLayout($contact, $category, $paragraphs),
            'system' => self::systemLayout($contact, $category, $paragraphs),
            default => self::contactLayout($contact, $category, $paragraphs),
        };
    }

    public static function layoutFor(Contact $contact): string
    {
        return DirectoryCategories::layoutForType($contact->type);
    }

    /**
     * @param  list<string>  $paragraphs
     * @return array<string, mixed>
     */
    private static function integrationLayout(Contact $contact, ?array $category, array $paragraphs): array
    {
        $catalog = DirectoryIntegrationCatalog::forContact($contact);
        $health = $contact->relationLoaded('connectionHealth')
            ? $contact->connectionHealth
            : $contact->connectionHealth()->first();
        $isApiLinked = $contact->isIntegrationCard();
        $credentialKey = $contact->resolvedCredentialKey();
        $credentialLabel = $credentialKey
            ? (\App\Models\IntegrationCredential::supportedKeys()[$credentialKey] ?? $credentialKey)
            : null;

        $statusLabel = $health?->statusLabel()
            ?? ($isApiLinked ? 'Not tested' : ($contact->is_active ? 'Active' : 'Inactive'));
        $statusVariant = $health?->statusBadgeVariant()
            ?? ($contact->is_active ? 'green' : 'gray');

        $whatFlows = $contact->data_flow ?: $catalog['data_flow'] ?? ($paragraphs[0] ?? null);
        $appLabel = DirectoryIntegrationCatalog::appLabelForContact($contact);
        $appRoute = DirectoryIntegrationCatalog::appRouteForContact($contact);
        $owningAgent = $contact->owning_agent ?: $catalog['owning_agent'] ?? null;
        $integrationType = $catalog['label'] ?? ($contact->job_title ?: 'Vendor / integration');

        $badges = [
            ['label' => $statusLabel, 'variant' => $statusVariant],
        ];

        if ($isApiLinked) {
            $badges[] = ['label' => 'API', 'variant' => 'gray'];
        }

        $actions = [
            ['type' => 'link', 'label' => 'Edit', 'href' => route('directory.edit', $contact->id), 'icon' => 'edit'],
        ];

        if ($isApiLinked) {
            $actions[] = [
                'type' => 'form',
                'label' => 'Test connection',
                'icon' => 'refresh',
                'action' => route('directory.test-connection', $contact->id),
            ];
        }

        $relatedLinks = [];

        if ($appLabel && $appRoute) {
            $relatedLinks[] = [
                'label' => $appLabel,
                'href' => route($appRoute),
                'suffix' => 'Open ›',
                'icon' => 'payroll',
            ];
        }

        $relatedLinks[] = [
            'label' => 'Other vendors',
            'href' => route('directory', ['category' => 'vendors']),
            'suffix' => 'Browse ›',
            'icon' => 'vendors',
        ];

        if ($owningAgent) {
            $relatedLinks[] = [
                'label' => 'Owning agent',
                'href' => url('/staff'),
                'suffix' => $owningAgent.' ›',
                'icon' => 'agent',
            ];
        }

        $errors30d = $health?->errors_30d ?? 0;
        $errorsVariant = $errors30d > 0 ? 'red' : 'green';

        return [
            'layout' => 'integration',
            'breadcrumb_label' => $category['detail_label'] ?? 'Vendors / integrations',
            'avatar_gradient' => 'from-[#ec4899] to-[#9d174d]',
            'badges' => $badges,
            'subtitle' => self::joinMeta([
                $integrationType,
                $appLabel ? 'synced to the '.$appLabel : null,
            ], ' · '),
            'profile_subtext' => self::joinMeta([
                $contact->job_title ?: 'Vendor / integration',
                $isApiLinked ? 'API integration' : 'Reference vendor',
            ], ' · '),
            'actions' => $actions,
            'main_sections' => [
                [
                    'title' => 'Integration',
                    'rows' => array_values(array_filter([
                        ['label' => 'Type', 'value' => $integrationType.($isApiLinked ? ' — API connected' : '')],
                        ['label' => 'What flows', 'value' => $whatFlows],
                        $credentialLabel ? ['label' => 'Linked credential', 'value' => $credentialLabel] : null,
                        ['label' => 'Credentials', 'value' => 'Global Settings — Credential Vault', 'href' => DirectoryIntegrationCatalog::credentialVaultUrl($credentialKey)],
                    ])),
                ],
                [
                    'title' => 'Support',
                    'rows' => [
                        ['label' => 'Account #', 'value' => $contact->provider_id, 'copyable' => true],
                        ['label' => 'Support line', 'value' => $contact->phone, 'href' => $contact->phoneTelUri()],
                        ['label' => 'Rep', 'value' => self::repLine($contact), 'href' => $contact->mailtoUri()],
                    ],
                ],
            ],
            'glance_title' => 'Connection Health',
            'glance_rows' => [
                ['label' => 'Status', 'value' => $statusLabel, 'badge_variant' => $statusVariant],
                ['label' => 'Last sync', 'value' => $health?->last_sync_at?->format('M j, g:i A') ?: '—'],
                ['label' => 'Last batch', 'value' => $health?->last_batch_at?->format('M j, g:i A') ?: '—'],
                ['label' => 'Errors (30d)', 'value' => (string) $errors30d, 'badge_variant' => $errorsVariant],
            ],
            'related_links' => $relatedLinks,
            'show_design_note' => false,
            'show_linked_clients' => false,
            'linked_clients_title' => 'Linked clients',
            'health_message' => $health?->message,
        ];
    }

    /**
     * @param  list<string>  $paragraphs
     * @return array<string, mixed>
     */
    private static function systemLayout(Contact $contact, ?array $category, array $paragraphs): array
    {
        $healthy = $contact->is_active;

        return [
            'layout' => 'system',
            'breadcrumb_label' => 'State systems & portals',
            'avatar_gradient' => 'from-[#475569] to-[#1e293b]',
            'badges' => [
                ['label' => 'State system', 'variant' => 'gray'],
                ['label' => 'RPA', 'variant' => 'gray'],
            ],
            'subtitle' => self::joinMeta([
                $contact->clinic_name ?: 'External system',
                'accessed by AI agent via RPA',
            ], ' · '),
            'profile_subtext' => self::joinMeta([
                $contact->job_title ?: $contact->type,
                $contact->clinic_name,
            ], ' · '),
            'actions' => [
                ['type' => 'link', 'label' => 'Edit', 'href' => route('directory.edit', $contact->id), 'icon' => 'edit'],
                ['type' => 'link', 'label' => 'Credentials', 'href' => route('settings.global', ['tab' => 'credential-vault']), 'icon' => 'key'],
            ],
            'main_sections' => [
                [
                    'title' => 'Purpose & Access',
                    'rows' => [
                        ['label' => 'Used for', 'value' => $paragraphs[0] ?? $contact->job_title],
                        ['label' => 'Access method', 'value' => $paragraphs[1] ?? ($contact->provider_id ? 'RPA via '.$contact->provider_id : null)],
                        ['label' => 'Credentials', 'value' => 'Global Settings — Credential Vault', 'href' => route('settings.global', ['tab' => 'credential-vault'])],
                        ['label' => 'Cadence', 'value' => $paragraphs[2] ?? null],
                    ],
                ],
                [
                    'title' => 'What the AI Agent Does Here',
                    'rows' => [
                        ['label' => 'On hiring', 'value' => $paragraphs[3] ?? ($paragraphs[1] ?? null)],
                        ['label' => 'Ongoing', 'value' => $paragraphs[4] ?? ($paragraphs[2] ?? null)],
                    ],
                ],
            ],
            'glance_title' => 'At a glance',
            'glance_rows' => [
                ['label' => 'Caregivers associated', 'value' => (string) $contact->clients_count],
                ['label' => 'Pending association', 'value' => '0', 'badge_variant' => 'amber'],
                ['label' => 'Last RPA run', 'value' => $contact->updated_at?->format('M j') ?: '—'],
                ['label' => 'Access health', 'value' => $healthy ? 'OK' : 'Check', 'badge_variant' => $healthy ? 'green' : 'amber'],
            ],
            'related_links' => [
                ['label' => 'Background Checks', 'href' => route('background-checks'), 'suffix' => 'Open ›', 'icon' => 'shield'],
                ['label' => 'Credential vault', 'href' => route('settings.global'), 'suffix' => 'Settings ›', 'icon' => 'key'],
                ['label' => 'Owning agent', 'href' => url('/staff'), 'suffix' => 'Staff & AI Agents ›', 'icon' => 'agent'],
            ],
            'show_design_note' => false,
            'show_linked_clients' => false,
            'linked_clients_title' => 'Linked clients',
        ];
    }

    /**
     * @param  list<string>  $paragraphs
     * @return array<string, mixed>
     */
    private static function payerLayout(Contact $contact, ?array $category, array $paragraphs): array
    {
        $coordinatorCount = $contact->relationLoaded('childContacts')
            ? $contact->childContacts->count()
            : $contact->childContacts()->count();

        $actions = self::editAction($contact);

        if ($contact->claim_channel === Contact::CLAIM_CHANNEL_AVAILITY) {
            $actions[] = ['type' => 'link', 'label' => 'Open Availity', 'href' => 'https://www.availity.com', 'icon' => 'external', 'external' => true];
        }

        $coordinatorRows = [];
        if ($contact->relationLoaded('childContacts') && $contact->childContacts->isNotEmpty()) {
            $first = $contact->childContacts->first();
            $coordinatorRows[] = [
                'label' => 'Coordinator ('.$first->name.')',
                'value' => $first->phone ?: $first->email,
                'href' => route('directory.show', $first->id),
            ];
        }

        if ($coordinatorCount > 1) {
            $coordinatorRows[] = [
                'label' => 'Coordinator (others)',
                'value' => '+ '.($coordinatorCount - 1).' coordinators across '.$contact->clients_count.' clients',
            ];
        }

        return [
            'layout' => 'payer',
            'breadcrumb_label' => $category['detail_label'] ?? 'Payers / MCOs',
            'avatar_gradient' => 'from-[#2563eb] to-[#1e40af]',
            'badges' => [
                ['label' => $contact->is_active ? 'Active payer' : 'Inactive payer', 'variant' => $contact->is_active ? 'green' : 'gray'],
            ],
            'subtitle' => self::joinMeta([
                'MICH',
                $contact->clients_count ? $contact->clients_count.' clients linked' : null,
                $contact->formattedContractedRate() ? $contact->formattedContractedRate().' contracted' : null,
            ]),
            'profile_subtext' => self::joinMeta([
                'Managed Care Organization (MCO)',
                $contact->clinic_name ?: 'MICH / Medicaid',
            ]),
            'actions' => $actions,
            'main_sections' => array_values(array_filter([
                [
                    'title' => 'Claims & submission',
                    'rows' => array_values(array_filter([
                        ['label' => 'Claim type', 'value' => $paragraphs[0] ?? 'EDI 837P · HCPCS T1019 (15-min units)'],
                        ['label' => 'Submission channel', 'value' => $contact->claimChannelLabel() ? str_replace('837P · ', '', $contact->claimChannelLabel()) : ($paragraphs[1] ?? null)],
                        ['label' => 'Payer ID', 'value' => $contact->provider_id, 'copyable' => true],
                        ['label' => 'Payment confirmation', 'value' => $paragraphs[2] ?? 'EOB / remittance from payer'],
                        ['label' => 'Contracted rate', 'value' => $contact->formattedContractedRate() ?: '—'],
                    ])),
                ],
                [
                    'title' => 'Contacts',
                    'rows' => [
                        ['label' => 'Provider services', 'value' => $contact->phone, 'href' => $contact->phoneTelUri()],
                        ['label' => 'Prior auth / PA line', 'value' => $contact->fax, 'href' => $contact->faxTelUri()],
                        ['label' => 'Provider portal', 'value' => $paragraphs[3] ?? 'availity.com · login in Global Settings (RPA)'],
                        ['label' => 'Network rep', 'value' => self::repLine($contact), 'href' => $contact->mailtoUri()],
                    ],
                ],
                $coordinatorRows !== [] ? [
                    'title' => 'Linked Case Coordinators',
                    'rows' => $coordinatorRows,
                ] : null,
            ])),
            'glance_title' => 'At a glance',
            'glance_rows' => [
                ['label' => 'Clients linked', 'value' => (string) $contact->clients_count],
                ['label' => 'Active PAs', 'value' => (string) max(0, $contact->clients_count - 2)],
                ['label' => 'PAs expiring ≤30d', 'value' => '0', 'badge_variant' => 'amber'],
                ['label' => 'Contracted rate', 'value' => $contact->formattedContractedRate() ?: '—', 'pill' => true],
            ],
            'related_links' => [],
            'show_design_note' => false,
            'show_linked_clients' => true,
            'linked_clients_title' => 'Linked clients (sample)',
        ];
    }

    /**
     * @param  list<string>  $paragraphs
     * @return array<string, mixed>
     */
    private static function aswLayout(Contact $contact, ?array $category, array $paragraphs): array
    {
        $actions = self::editAction($contact);

        if ($contact->mailtoUri()) {
            $actions[] = ['type' => 'link', 'label' => 'Email', 'href' => $contact->mailtoUri(), 'icon' => 'email'];
        }

        if ($contact->faxTelUri()) {
            $actions[] = ['type' => 'link', 'label' => 'eFax', 'href' => $contact->faxTelUri(), 'icon' => 'fax'];
        }

        return [
            'layout' => 'asw',
            'breadcrumb_label' => $category['detail_label'] ?? 'DHS — ASWs',
            'avatar_gradient' => 'from-[#8b5cf6] to-[#6d28d9]',
            'badges' => [
                ['label' => 'DHS ASW', 'variant' => 'purple'],
            ],
            'subtitle' => self::joinMeta([
                $contact->job_title ?: 'Adult Services Worker',
                $contact->clinic_name ?: 'MDHHS',
                $contact->county ? $contact->county.' County' : null,
                $contact->clients_count ? $contact->clients_count.' clients linked' : null,
            ]),
            'profile_subtext' => self::joinMeta([
                'Adult Services Worker (ASW)',
                $contact->clinic_name ?: 'MDHHS — Michigan Dept. of Health & Human Services',
            ]),
            'actions' => $actions,
            'main_sections' => [
                [
                    'title' => 'Role & submission',
                    'rows' => [
                        ['label' => 'Authorizes', 'value' => $paragraphs[0] ?? 'DHS Home Help — Time/Task Sheets'],
                        ['label' => 'Receives from us', 'value' => $paragraphs[1] ?? 'Home Help Invoice — emailed / eFaxed monthly'],
                        ['label' => 'Payment path', 'value' => $paragraphs[2] ?? 'Sigma Portal (posts Tue/Wed → paid Friday)'],
                        ['label' => 'Coverage', 'value' => self::joinMeta([$contact->county ? $contact->county.' County' : null, $contact->city], ' · ') ?: null],
                    ],
                ],
                [
                    'title' => 'Contacts',
                    'rows' => [
                        ['label' => 'Office phone', 'value' => $contact->phone, 'href' => $contact->phoneTelUri()],
                        ['label' => 'eFax (invoices)', 'value' => $contact->fax, 'href' => $contact->faxTelUri()],
                        ['label' => 'Email', 'value' => $contact->email, 'href' => $contact->mailtoUri()],
                        ['label' => 'Office address', 'value' => $contact->formattedAddress()],
                    ],
                ],
            ],
            'glance_title' => 'At a glance',
            'glance_rows' => [
                ['label' => 'Clients linked', 'value' => (string) $contact->clients_count],
                ['label' => 'Invoices sent (May)', 'value' => (string) $contact->clients_count],
                ['label' => 'Reassessments due ≤30d', 'value' => '0', 'badge_variant' => 'amber'],
            ],
            'related_links' => [],
            'show_design_note' => false,
            'show_linked_clients' => true,
            'linked_clients_title' => 'Linked clients (DHS)',
        ];
    }

    /**
     * @param  list<string>  $paragraphs
     * @return array<string, mixed>
     */
    private static function coordinatorLayout(Contact $contact, ?array $category, array $paragraphs): array
    {
        $parent = $contact->relationLoaded('parentContact')
            ? $contact->parentContact
            : $contact->parentContact()->first();

        $actions = self::editAction($contact);

        if ($contact->phoneTelUri()) {
            $actions[] = ['type' => 'link', 'label' => 'Call', 'href' => $contact->phoneTelUri(), 'icon' => 'phone'];
        }

        if ($contact->mailtoUri()) {
            $actions[] = ['type' => 'link', 'label' => 'Email', 'href' => $contact->mailtoUri(), 'icon' => 'email'];
        }

        $planRow = $parent
            ? ['label' => 'Plan', 'value' => $parent->name, 'href' => route('directory.show', $parent->id)]
            : ['label' => 'Plan', 'value' => $contact->clinic_name];

        return [
            'layout' => 'coordinator',
            'breadcrumb_label' => $category['detail_label'] ?? 'MICH — Case Coordinators',
            'avatar_gradient' => 'from-[#0ea5e9] to-[#0369a1]',
            'badges' => [
                ['label' => 'Case Coordinator', 'variant' => 'blue'],
            ],
            'subtitle' => self::joinMeta([
                $parent?->name ?: $contact->clinic_name,
                $contact->clients_count ? $contact->clients_count.' clients linked' : null,
            ]),
            'profile_subtext' => self::joinMeta([
                'MICH Case Coordinator',
                $parent?->name ?: $contact->clinic_name,
            ]),
            'actions' => $actions,
            'main_sections' => [
                [
                    'title' => 'Role',
                    'rows' => [
                        ['label' => 'Manages', 'value' => $paragraphs[0] ?? 'Prior Authorizations (PA) · service requests for members'],
                        ['label' => 'We contact to', 'value' => $paragraphs[1] ?? 'Request services for a new client · renew a PA (2 wks before expiry)'],
                        $planRow,
                    ],
                ],
                [
                    'title' => 'Contacts',
                    'rows' => [
                        ['label' => 'Direct line', 'value' => $contact->phone, 'href' => $contact->phoneTelUri()],
                        ['label' => 'Email', 'value' => $contact->email, 'href' => $contact->mailtoUri()],
                        ['label' => 'Best for', 'value' => $paragraphs[2] ?? 'PA status, renewal packets, service changes'],
                    ],
                ],
            ],
            'glance_title' => 'At a glance',
            'glance_rows' => [
                ['label' => 'Clients linked', 'value' => (string) $contact->clients_count],
                ['label' => 'Active PAs', 'value' => (string) max(0, $contact->clients_count - 1)],
                ['label' => 'PA renewals ≤30d', 'value' => '0', 'badge_variant' => 'amber'],
                ['label' => 'Open requests', 'value' => '0'],
            ],
            'related_links' => [],
            'show_design_note' => false,
            'show_linked_clients' => true,
            'linked_clients_title' => 'Linked clients (MICH)',
        ];
    }

    /**
     * @param  list<string>  $paragraphs
     * @return array<string, mixed>
     */
    private static function physicianLayout(Contact $contact, ?array $category, array $paragraphs): array
    {
        $actions = self::editAction($contact);

        if ($contact->faxTelUri()) {
            $actions[] = ['type' => 'link', 'label' => 'Fax orders', 'href' => $contact->faxTelUri(), 'icon' => 'fax'];
        }

        return [
            'layout' => 'physician',
            'breadcrumb_label' => $category['detail_label'] ?? 'Physicians / PCPs',
            'avatar_gradient' => 'from-[#10b981] to-[#047857]',
            'badges' => [
                ['label' => 'PCP', 'variant' => 'green'],
            ],
            'subtitle' => self::joinMeta([
                $contact->job_title ?: 'Family Medicine',
                $contact->clinic_name,
                $contact->clients_count ? $contact->clients_count.' clients' : null,
            ]),
            'profile_subtext' => self::joinMeta([
                'Primary Care Physician',
                $contact->job_title ?: 'Family Medicine',
            ]),
            'actions' => $actions,
            'main_sections' => [
                [
                    'title' => 'Used for',
                    'rows' => [
                        ['label' => 'Provides', 'value' => $paragraphs[0] ?? 'Signed plan of care / medical needs verification at intake & reassessment'],
                        ['label' => 'NPI', 'value' => $contact->provider_id, 'copyable' => true],
                        ['label' => 'Practice', 'value' => $contact->clinic_name],
                    ],
                ],
                [
                    'title' => 'Contacts',
                    'rows' => array_values(array_filter([
                        ['label' => 'Office phone', 'value' => $contact->phone, 'href' => $contact->phoneTelUri()],
                        ['label' => 'Fax (orders)', 'value' => $contact->fax, 'href' => $contact->faxTelUri()],
                        ['label' => 'Email', 'value' => $contact->email, 'href' => $contact->mailtoUri()],
                        ['label' => 'Records portal', 'value' => $paragraphs[1] ?? null],
                    ])),
                ],
            ],
            'glance_title' => 'At a glance',
            'glance_rows' => [
                ['label' => 'Clients under care', 'value' => (string) $contact->clients_count],
                ['label' => 'Care plans on file', 'value' => (string) $contact->clients_count],
                ['label' => 'Orders pending', 'value' => '0', 'badge_variant' => 'amber'],
            ],
            'related_links' => [],
            'show_design_note' => false,
            'show_linked_clients' => true,
            'linked_clients_title' => 'Patients (clients)',
        ];
    }

    /**
     * @param  list<string>  $paragraphs
     * @return array<string, mixed>
     */
    private static function referralLayout(Contact $contact, ?array $category, array $paragraphs): array
    {
        $actions = self::editAction($contact);

        if ($contact->mailtoUri()) {
            $actions[] = ['type' => 'link', 'label' => 'Email', 'href' => $contact->mailtoUri(), 'icon' => 'email'];
        }

        return [
            'layout' => 'referral',
            'breadcrumb_label' => $category['detail_label'] ?? 'Referral sources',
            'avatar_gradient' => 'from-[#f59e0b] to-[#b45309]',
            'badges' => [
                ['label' => 'Referral source', 'variant' => 'amber'],
            ],
            'subtitle' => self::joinMeta([
                $contact->job_title ?: 'Hospital discharge planning',
                $contact->clients_count ? $contact->clients_count.' referrals tracked' : null,
            ]),
            'profile_subtext' => self::joinMeta([
                'Referral source',
                $contact->job_title ?: 'hospital case management',
            ]),
            'actions' => $actions,
            'main_sections' => [
                [
                    'title' => 'Relationship',
                    'rows' => [
                        ['label' => 'Type', 'value' => $paragraphs[0] ?? 'Inbound referrals (we receive) — discharge to home care'],
                        ['label' => 'Referrals YTD', 'value' => $paragraphs[1] ?? ($contact->clients_count ? $contact->clients_count.' received' : null)],
                        ['label' => 'Primary contact', 'value' => $contact->job_title],
                    ],
                ],
                [
                    'title' => 'Contacts',
                    'rows' => [
                        ['label' => 'Phone', 'value' => $contact->phone, 'href' => $contact->phoneTelUri()],
                        ['label' => 'Referral fax / email', 'value' => self::joinMeta([$contact->fax, $contact->email], ' · ')],
                    ],
                ],
            ],
            'glance_title' => 'Referral performance',
            'glance_rows' => [
                ['label' => 'Received YTD', 'value' => (string) $contact->clients_count],
                ['label' => 'Converted to active', 'value' => (string) max(0, (int) round($contact->clients_count * 0.77))],
                ['label' => 'Conversion rate', 'value' => $contact->clients_count > 0 ? '77%' : '—', 'badge_variant' => 'green'],
                ['label' => 'Pending intake', 'value' => '0', 'badge_variant' => 'amber'],
            ],
            'related_links' => [],
            'show_design_note' => false,
            'show_linked_clients' => true,
            'linked_clients_title' => 'Recent referrals',
        ];
    }

    /**
     * @param  list<string>  $paragraphs
     * @return array<string, mixed>
     */
    private static function pharmacyLayout(Contact $contact, ?array $category, array $paragraphs): array
    {
        $actions = self::editAction($contact);

        if ($contact->phoneTelUri()) {
            $actions[] = ['type' => 'link', 'label' => 'Call', 'href' => $contact->phoneTelUri(), 'icon' => 'phone'];
        }

        return [
            'layout' => 'pharmacy',
            'breadcrumb_label' => $category['detail_label'] ?? 'Pharmacies / facilities',
            'avatar_gradient' => 'from-[#0ea5e9] to-[#0e7490]',
            'badges' => [
                ['label' => 'Pharmacy', 'variant' => 'gray'],
            ],
            'subtitle' => self::joinMeta([
                $contact->clients_count ? 'Used by '.$contact->clients_count.' clients' : null,
                $paragraphs[0] ?? null,
            ]),
            'profile_subtext' => self::joinMeta([
                'Pharmacy',
                'reference contact for client medication coordination',
            ]),
            'actions' => $actions,
            'main_sections' => [
                [
                    'title' => 'Reference',
                    'rows' => [
                        ['label' => 'Type', 'value' => $paragraphs[0] ?? 'Retail pharmacy (non-billed) — coordination only'],
                        ['label' => 'Used by', 'value' => $contact->clients_count ? $contact->clients_count.' clients' : null],
                        ['label' => 'Languages', 'value' => $paragraphs[1] ?? null],
                    ],
                ],
                [
                    'title' => 'Contacts',
                    'rows' => [
                        ['label' => 'Phone', 'value' => $contact->phone, 'href' => $contact->phoneTelUri()],
                        ['label' => 'Fax', 'value' => $contact->fax, 'href' => $contact->faxTelUri()],
                        ['label' => 'Address', 'value' => $contact->formattedAddress()],
                    ],
                ],
            ],
            'glance_title' => 'At a glance',
            'glance_rows' => [
                ['label' => 'Clients using', 'value' => (string) $contact->clients_count],
                ['label' => 'Home delivery', 'value' => '0'],
                ['label' => 'Type', 'value' => 'Non-billed', 'badge_variant' => 'gray'],
            ],
            'related_links' => [],
            'show_design_note' => false,
            'show_linked_clients' => true,
            'linked_clients_title' => 'Linked clients',
        ];
    }

    /**
     * @param  list<string>  $paragraphs
     * @return array<string, mixed>
     */
    private static function contactLayout(Contact $contact, ?array $category, array $paragraphs): array
    {
        $actions = [
            ['type' => 'link', 'label' => 'Edit', 'href' => route('directory.edit', $contact->id), 'icon' => 'edit'],
        ];

        if ($contact->phoneTelUri()) {
            $actions[] = ['type' => 'link', 'label' => 'Call', 'href' => $contact->phoneTelUri(), 'icon' => 'phone'];
        }

        if ($contact->mailtoUri()) {
            $actions[] = ['type' => 'link', 'label' => 'Email', 'href' => $contact->mailtoUri(), 'icon' => 'email'];
        }

        $mainSections = [
            [
                'title' => 'Role & Details',
                'rows' => [
                    ['label' => 'Job title', 'value' => $contact->job_title],
                    ['label' => 'Organization', 'value' => $contact->clinic_name],
                    ['label' => 'Provider ID', 'value' => $contact->provider_id, 'copyable' => true],
                    ['label' => 'Type', 'value' => $contact->type],
                ],
            ],
            [
                'title' => 'Contacts',
                'rows' => [
                    ['label' => 'Phone', 'value' => $contact->phone, 'href' => $contact->phoneTelUri()],
                    ['label' => 'Fax', 'value' => $contact->fax, 'href' => $contact->faxTelUri()],
                    ['label' => 'Email', 'value' => $contact->email, 'href' => $contact->mailtoUri()],
                ],
            ],
        ];

        if ($contact->formattedAddress() || $contact->county) {
            $mainSections[] = [
                'title' => 'Address',
                'rows' => [
                    ['label' => 'Street', 'value' => trim(($contact->address_line1 ?? '').' '.($contact->address_line2 ?? '')) ?: null],
                    ['label' => 'City', 'value' => $contact->city],
                    ['label' => 'County', 'value' => $contact->county],
                    ['label' => 'State / ZIP', 'value' => trim(collect([$contact->state, $contact->zip])->filter()->implode(' ')) ?: null],
                ],
                'maps_url' => $contact->mapsUrl(),
            ];
        }

        if ($contact->notes) {
            $mainSections[] = [
                'title' => 'Notes',
                'rows' => [
                    ['label' => 'Details', 'value' => $contact->notes, 'multiline' => true],
                ],
            ];
        }

        return [
            'layout' => 'contact',
            'breadcrumb_label' => $category['label'] ?? $contact->type,
            'avatar_gradient' => 'from-[#2563eb] to-[#1e40af]',
            'badges' => [
                ['label' => $contact->type, 'variant' => 'blue'],
                ['label' => $contact->is_active ? 'Active' : 'Inactive', 'variant' => $contact->is_active ? 'green' : 'gray'],
            ],
            'subtitle' => self::joinMeta(array_filter([
                $contact->clinic_name,
                $contact->clients_count ? $contact->clients_count.' '.Str::plural('client', $contact->clients_count).' linked' : null,
                $contact->job_title,
            ]), ' · '),
            'profile_subtext' => self::joinMeta([$contact->type, $contact->clinic_name], ' · '),
            'actions' => $actions,
            'main_sections' => $mainSections,
            'glance_title' => 'At a glance',
            'glance_rows' => [
                ['label' => 'Clients linked', 'value' => (string) $contact->clients_count],
                ['label' => 'Status', 'value' => $contact->is_active ? 'Active' : 'Inactive', 'badge_variant' => $contact->is_active ? 'green' : 'gray'],
                ['label' => 'Contact type', 'value' => $contact->type],
            ],
            'related_links' => [],
            'show_design_note' => false,
            'show_linked_clients' => true,
            'linked_clients_title' => 'Linked clients',
        ];
    }

    /**
     * @return list<array{type: string, label: string, href: string, icon: string}>
     */
    private static function editAction(Contact $contact): array
    {
        return [
            ['type' => 'link', 'label' => 'Edit', 'href' => route('directory.edit', $contact->id), 'icon' => 'edit'],
        ];
    }

    /**
     * @return list<string>
     */
    private static function noteParagraphs(?string $notes): array
    {
        if (blank($notes)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R\R+/', $notes) ?: [])));
    }

    /**
     * @param  list<?string>  $parts
     */
    private static function joinMeta(array $parts, string $separator = ' · '): string
    {
        $filtered = array_values(array_filter($parts, fn ($part) => filled($part)));

        return implode($separator, $filtered);
    }

    private static function repLine(Contact $contact): ?string
    {
        if ($contact->name && $contact->email) {
            return $contact->name.' — '.$contact->email;
        }

        return $contact->email ?: $contact->name;
    }
}

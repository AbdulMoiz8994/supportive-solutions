<?php

namespace App\Support;

use App\Models\Contact;

class DirectoryIndexLayout
{
    /**
     * @return array{
     *     table_key: string,
     *     panel_title: string,
     *     panel_subtitle: string,
     *     filter_placeholder: string,
     *     chips: list<array{label: string, param: string, value: ?string}>,
     *     sort_options: list<array{value: string, label: string}>,
     *     columns: list<array{key: string, label: string, sortable?: bool}>,
     *     footer_template: string,
     * }
     */
    public static function forCategory(?array $category): array
    {
        if ($category === null) {
            return self::defaultLayout();
        }

        return match ($category['key']) {
            'payers' => self::payersLayout($category),
            'asws' => self::aswsLayout($category),
            'coordinators' => self::coordinatorsLayout($category),
            'physicians' => self::physiciansLayout($category),
            'referrals' => self::referralsLayout($category),
            'state_systems' => self::stateSystemsLayout($category),
            'vendors' => self::vendorsLayout($category),
            'pharmacies' => self::pharmaciesLayout($category),
            default => self::defaultLayout($category),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultLayout(?array $category = null): array
    {
        return [
            'table_key' => 'default',
            'panel_title' => $category['label'] ?? 'All Directory Contacts',
            'panel_subtitle' => $category['panel_subtitle'] ?? 'Search, filter, and open any entry for full contact details.',
            'filter_placeholder' => 'Filter contacts…',
            'chips' => [
                ['label' => 'All', 'param' => 'status', 'value' => null],
                ['label' => 'Active', 'param' => 'status', 'value' => 'active'],
                ['label' => 'Inactive', 'param' => 'status', 'value' => 'inactive'],
            ],
            'sort_options' => self::baseSortOptions(),
            'columns' => [
                ['key' => 'name', 'label' => 'Name', 'sortable' => true],
                ['key' => 'type', 'label' => 'Type', 'sortable' => true],
                ['key' => 'clinic_name', 'label' => 'Organization', 'sortable' => true],
                ['key' => 'phone', 'label' => 'Phone', 'sortable' => true],
                ['key' => 'email', 'label' => 'Email', 'sortable' => true],
                ['key' => 'clients_count', 'label' => 'Linked Clients', 'sortable' => false],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
            ],
            'footer_template' => ':count contacts in this view',
        ];
    }

    /**
     * @param  array<string, mixed>  $category
     * @return array<string, mixed>
     */
    private static function payersLayout(array $category): array
    {
        return [
            'table_key' => 'payers',
            'panel_title' => $category['label'].' — MICH',
            'panel_subtitle' => $category['panel_subtitle'],
            'filter_placeholder' => 'Filter payers…',
            'chips' => [
                ['label' => 'All', 'param' => 'claim_channel', 'value' => null],
                ['label' => 'Availity', 'param' => 'claim_channel', 'value' => Contact::CLAIM_CHANNEL_AVAILITY],
                ['label' => 'Separate EDI', 'param' => 'claim_channel', 'value' => Contact::CLAIM_CHANNEL_SEPARATE_EDI],
                ['label' => 'Most clients', 'param' => 'sort', 'value' => 'clients_count'],
            ],
            'sort_options' => array_merge(self::baseSortOptions(), [
                ['value' => 'clients_count', 'label' => 'Sort: Linked clients'],
                ['value' => 'contracted_rate', 'label' => 'Sort: Contracted rate'],
            ]),
            'columns' => [
                ['key' => 'name', 'label' => 'Plan', 'sortable' => true],
                ['key' => 'claim_channel', 'label' => 'Claim channel', 'sortable' => false],
                ['key' => 'phone', 'label' => 'Provider line', 'sortable' => true],
                ['key' => 'contracted_rate', 'label' => 'Contracted rate', 'sortable' => true],
                ['key' => 'clients_count', 'label' => 'Linked clients', 'sortable' => false],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
            ],
            'footer_template' => ':count plans · :linked_clients linked clients',
        ];
    }

    /**
     * @param  array<string, mixed>  $category
     * @return array<string, mixed>
     */
    private static function aswsLayout(array $category): array
    {
        return [
            'table_key' => 'asws',
            'panel_title' => $category['label'],
            'panel_subtitle' => $category['panel_subtitle'],
            'filter_placeholder' => 'Filter ASWs…',
            'chips' => self::statusChips(),
            'sort_options' => array_merge(self::baseSortOptions(), [
                ['value' => 'clients_count', 'label' => 'Sort: Linked clients'],
            ]),
            'columns' => [
                ['key' => 'name', 'label' => 'Worker', 'sortable' => true],
                ['key' => 'county', 'label' => 'Coverage', 'sortable' => false],
                ['key' => 'phone', 'label' => 'Office phone', 'sortable' => true],
                ['key' => 'fax', 'label' => 'eFax', 'sortable' => false],
                ['key' => 'clients_count', 'label' => 'Linked clients', 'sortable' => false],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
            ],
            'footer_template' => ':count workers · :linked_clients DHS clients linked',
        ];
    }

    /**
     * @param  array<string, mixed>  $category
     * @return array<string, mixed>
     */
    private static function coordinatorsLayout(array $category): array
    {
        return [
            'table_key' => 'coordinators',
            'panel_title' => $category['label'],
            'panel_subtitle' => $category['panel_subtitle'],
            'filter_placeholder' => 'Filter coordinators…',
            'chips' => self::statusChips(),
            'sort_options' => array_merge(self::baseSortOptions(), [
                ['value' => 'clients_count', 'label' => 'Sort: Linked clients'],
            ]),
            'columns' => [
                ['key' => 'name', 'label' => 'Coordinator', 'sortable' => true],
                ['key' => 'clinic_name', 'label' => 'Plan', 'sortable' => true],
                ['key' => 'phone', 'label' => 'Direct line', 'sortable' => true],
                ['key' => 'email', 'label' => 'Email', 'sortable' => true],
                ['key' => 'clients_count', 'label' => 'Linked clients', 'sortable' => false],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
            ],
            'footer_template' => ':count coordinators · :linked_clients MICH clients linked',
        ];
    }

    /**
     * @param  array<string, mixed>  $category
     * @return array<string, mixed>
     */
    private static function physiciansLayout(array $category): array
    {
        return [
            'table_key' => 'physicians',
            'panel_title' => $category['label'],
            'panel_subtitle' => $category['panel_subtitle'],
            'filter_placeholder' => 'Filter physicians…',
            'chips' => self::statusChips(),
            'sort_options' => array_merge(self::baseSortOptions(), [
                ['value' => 'clients_count', 'label' => 'Sort: Patients'],
            ]),
            'columns' => [
                ['key' => 'name', 'label' => 'Physician', 'sortable' => true],
                ['key' => 'provider_id', 'label' => 'NPI', 'sortable' => false],
                ['key' => 'clinic_name', 'label' => 'Practice', 'sortable' => true],
                ['key' => 'fax', 'label' => 'Fax (orders)', 'sortable' => false],
                ['key' => 'clients_count', 'label' => 'Clients', 'sortable' => false],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
            ],
            'footer_template' => ':count providers · :linked_clients clients under care',
        ];
    }

    /**
     * @param  array<string, mixed>  $category
     * @return array<string, mixed>
     */
    private static function referralsLayout(array $category): array
    {
        return [
            'table_key' => 'referrals',
            'panel_title' => $category['label'],
            'panel_subtitle' => $category['panel_subtitle'],
            'filter_placeholder' => 'Filter referral sources…',
            'chips' => self::statusChips(),
            'sort_options' => array_merge(self::baseSortOptions(), [
                ['value' => 'clients_count', 'label' => 'Sort: Referrals'],
            ]),
            'columns' => [
                ['key' => 'name', 'label' => 'Source', 'sortable' => true],
                ['key' => 'job_title', 'label' => 'Primary contact', 'sortable' => false],
                ['key' => 'phone', 'label' => 'Phone', 'sortable' => true],
                ['key' => 'fax', 'label' => 'Referral fax', 'sortable' => false],
                ['key' => 'clients_count', 'label' => 'Linked clients', 'sortable' => false],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
            ],
            'footer_template' => ':count sources · :linked_clients referrals tracked',
        ];
    }

    /**
     * @param  array<string, mixed>  $category
     * @return array<string, mixed>
     */
    private static function stateSystemsLayout(array $category): array
    {
        return [
            'table_key' => 'state_systems',
            'panel_title' => $category['label'],
            'panel_subtitle' => $category['panel_subtitle'],
            'filter_placeholder' => 'Filter systems…',
            'chips' => self::statusChips(),
            'sort_options' => self::baseSortOptions(),
            'columns' => [
                ['key' => 'name', 'label' => 'System', 'sortable' => true],
                ['key' => 'job_title', 'label' => 'Purpose', 'sortable' => false],
                ['key' => 'clinic_name', 'label' => 'Agency', 'sortable' => true],
                ['key' => 'provider_id', 'label' => 'Access', 'sortable' => false],
                ['key' => 'clients_count', 'label' => 'Linked records', 'sortable' => false],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
            ],
            'footer_template' => ':count systems in directory',
        ];
    }

    /**
     * @param  array<string, mixed>  $category
     * @return array<string, mixed>
     */
    private static function vendorsLayout(array $category): array
    {
        return [
            'table_key' => 'vendors',
            'panel_title' => $category['label'],
            'panel_subtitle' => $category['panel_subtitle'],
            'filter_placeholder' => 'Filter vendors…',
            'chips' => self::statusChips(),
            'sort_options' => self::baseSortOptions(),
            'columns' => [
                ['key' => 'name', 'label' => 'Vendor', 'sortable' => true],
                ['key' => 'job_title', 'label' => 'Integration type', 'sortable' => false],
                ['key' => 'data_flow', 'label' => 'What flows', 'sortable' => false],
                ['key' => 'phone', 'label' => 'Support line', 'sortable' => true],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
            ],
            'footer_template' => ':count vendors · integrations directory',
        ];
    }

    /**
     * @param  array<string, mixed>  $category
     * @return array<string, mixed>
     */
    private static function pharmaciesLayout(array $category): array
    {
        return [
            'table_key' => 'pharmacies',
            'panel_title' => $category['label'],
            'panel_subtitle' => $category['panel_subtitle'],
            'filter_placeholder' => 'Filter pharmacies…',
            'chips' => self::statusChips(),
            'sort_options' => array_merge(self::baseSortOptions(), [
                ['value' => 'clients_count', 'label' => 'Sort: Clients using'],
            ]),
            'columns' => [
                ['key' => 'name', 'label' => 'Facility', 'sortable' => true],
                ['key' => 'city', 'label' => 'City', 'sortable' => true],
                ['key' => 'phone', 'label' => 'Phone', 'sortable' => true],
                ['key' => 'fax', 'label' => 'Fax', 'sortable' => false],
                ['key' => 'clients_count', 'label' => 'Clients using', 'sortable' => false],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
            ],
            'footer_template' => ':count entries · :linked_clients clients using',
        ];
    }

    /**
     * @return list<array{label: string, param: string, value: ?string}>
     */
    private static function statusChips(): array
    {
        return [
            ['label' => 'All', 'param' => 'status', 'value' => null],
            ['label' => 'Active', 'param' => 'status', 'value' => 'active'],
            ['label' => 'Inactive', 'param' => 'status', 'value' => 'inactive'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function baseSortOptions(): array
    {
        return [
            ['value' => 'name', 'label' => 'Sort: Name'],
            ['value' => 'type', 'label' => 'Sort: Type'],
            ['value' => 'clinic_name', 'label' => 'Sort: Organization'],
            ['value' => 'created_at', 'label' => 'Sort: Recently added'],
        ];
    }
}

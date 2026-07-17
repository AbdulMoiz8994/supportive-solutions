<?php

return [
    'datasets' => [
        'clients' => [
            'label' => 'Clients',
            'model' => \App\Models\Client::class,
            'columns' => [
                'name' => ['label' => 'Client', 'accessor' => 'full_name'],
                'status' => ['label' => 'Status', 'field' => 'status'],
                'county' => ['label' => 'County', 'field' => 'county'],
                'program' => ['label' => 'Program', 'field' => 'coverage_type_id'],
            ],
            'filters' => ['status', 'county', 'program', 'date_range'],
            'group_by' => ['county', 'status'],
            'aggregates' => ['count'],
        ],
        'caregivers' => [
            'label' => 'Caregivers',
            'model' => \App\Models\Employee::class,
            'columns' => [
                'name' => ['label' => 'Caregiver', 'accessor' => 'full_name'],
                'status' => ['label' => 'Status', 'field' => 'status'],
                'position' => ['label' => 'Position', 'field' => 'position'],
            ],
            'filters' => ['status', 'date_range'],
            'group_by' => ['status', 'position'],
            'aggregates' => ['count'],
        ],
        'visits' => [
            'label' => 'Visits',
            'model' => \App\Models\Schedule::class,
            'scope' => 'care_visit',
            'columns' => [
                'caregiver' => ['label' => 'Caregiver', 'relation' => 'employee'],
                'client' => ['label' => 'Client', 'relation' => 'client'],
                'date' => ['label' => 'Date', 'field' => 'date'],
                'status' => ['label' => 'Status', 'field' => 'status'],
                'hours' => ['label' => 'Hours', 'field' => 'total_hours'],
            ],
            'filters' => ['status', 'date_range', 'employee_id', 'client_id', 'program'],
            'group_by' => ['employee_id', 'status', 'client_id'],
            'aggregates' => ['count', 'sum_hours'],
        ],
        'authorizations' => [
            'label' => 'Authorizations',
            'model' => \App\Models\CareDetail::class,
            'columns' => [
                'client' => ['label' => 'Client', 'relation' => 'client'],
                'billing_code' => ['label' => 'Code', 'field' => 'billing_code'],
                'status' => ['label' => 'Status', 'field' => 'status'],
                'total_units' => ['label' => 'Units', 'field' => 'total_units'],
                'end_date' => ['label' => 'Expires', 'field' => 'end_date'],
            ],
            'filters' => ['status', 'date_range'],
            'group_by' => ['status', 'billing_code'],
            'aggregates' => ['count', 'sum_units'],
        ],
        'documents' => [
            'label' => 'Documents',
            'model' => \App\Models\Document::class,
            'columns' => [
                'name' => ['label' => 'Document', 'field' => 'name'],
                'category' => ['label' => 'Category', 'field' => 'category'],
                'verification_status' => ['label' => 'Status', 'field' => 'verification_status'],
                'created_at' => ['label' => 'Uploaded', 'field' => 'created_at'],
            ],
            'filters' => ['status', 'date_range'],
            'group_by' => ['category', 'verification_status'],
            'aggregates' => ['count'],
        ],
        'billing' => [
            'label' => 'Billing / Payments',
            'model' => \App\Models\BillingClaimAudit::class,
            'columns' => [
                'client' => ['label' => 'Client', 'relation' => 'client'],
                'claim_number' => ['label' => 'Claim', 'field' => 'claim_number'],
                'status' => ['label' => 'Status', 'field' => 'claim_status'],
                'billed_amount' => ['label' => 'Billed', 'field' => 'total_amount'],
                'paid_amount' => ['label' => 'Paid', 'field' => 'paid_amount'],
            ],
            'filters' => ['status', 'date_range', 'program'],
            'group_by' => ['claim_status', 'program'],
            'aggregates' => ['count', 'sum_billed', 'sum_paid'],
        ],
    ],

    'chart_types' => [
        'bar' => 'Bar chart',
        'line' => 'Line chart',
        'pie' => 'Pie chart',
        'table' => 'Table only',
    ],
];

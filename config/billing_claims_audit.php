<?php

return [
    'coverage_types' => [
        'medicaid_home_help',
        'medicaid_mich',
        'individual_health_plan',
        'private_pay',
        'medicare',
    ],

    'billing_methods' => [
        'email_case_coordinator',
        'payer_portal',
        'manual_upload',
        'future_integration',
    ],

    'billing_routes' => [
        'sigma_portal',
        'availity_837p',
        'email_asw',
        'manual_invoice',
    ],

    'authorization_statuses' => [
        'active',
        'expiring_soon',
        'expired',
        'missing',
        'needs_review',
    ],

    'billing_statuses' => [
        'not_ready',
        'ready_to_bill',
        'blocked',
        'draft',
        'sent',
        'submitted',
        'pending_payment',
        'paid',
        'partially_paid',
        'denied',
        'underpaid',
        'voided',
        'needs_review',
    ],

    'audit_statuses' => [
        'not_reviewed',
        'in_review',
        'passed',
        'issue_found',
        'needs_staff_review',
        'resolved',
        'escalated',
    ],

    'payment_statuses' => [
        'paid_full',
        'partial',
        'underpaid',
        'overpaid',
        'denied',
        'missing_eob',
        'pending',
        'not_received',
    ],

    'evv_statuses' => [
        'verified',
        'verified_local',
        'pending',
        'pending_sync',
        'missing',
        'exempt',
        'not_connected',
    ],

    'visit_verification_statuses' => [
        'verified',
        'pending',
        'missing',
        'partial',
    ],

    'ai_extraction_statuses' => [
        'not_connected',
        'pending_manual_review',
        'extracted',
        'failed',
    ],

    'issue_flag_types' => [
        'missing_authorization',
        'expired_authorization',
        'expiring_authorization',
        'over_authorized_hours',
        'missing_visit_verification',
        'missing_eob',
        'underpayment',
        'partial_payment',
        'denial',
        'overbilling',
        'manual_override',
        'needs_staff_review',
    ],

    'default_unit_minutes' => 15,
    'standard_billing_code' => 'T019',
    'standard_unit_codes' => ['T019', 'T1019'],
    'expiring_authorization_days' => 21,
    'eob_max_upload_kb' => 10240,
    'eob_allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    'default_asw_email' => env('BILLING_DEFAULT_ASW_EMAIL'),
    'sigma_portal_url' => env('BILLING_SIGMA_PORTAL_URL', 'https://www.michigan.gov/mdhhs'),
];

<?php

use App\Models\IntegrationCredential;

return [

    'programs' => ['MICH', 'DHS', 'ICO', 'DAAA'],

    'action_modes' => [
        'auto' => 'Auto-do',
        'queue' => 'Send to approval queue',
        'monitor' => 'Monitor only',
    ],

    /**
     * Per-agent action keys and default autonomy when seeding from catalog.
     */
    'agent_actions' => [
        'intake' => [
            ['key' => 'verify_eligibility', 'label' => 'Verify eligibility', 'default' => 'queue'],
            ['key' => 'build_chart', 'label' => 'Build client chart', 'default' => 'auto'],
            ['key' => 'activate_client', 'label' => 'Activate client', 'default' => 'queue'],
        ],
        'authorizations' => [
            ['key' => 'track_expiry', 'label' => 'Track PA expiry', 'default' => 'auto'],
            ['key' => 'assemble_packet', 'label' => 'Assemble renewal packet', 'default' => 'auto'],
            ['key' => 'submit_renewal', 'label' => 'Submit renewal to MCO', 'default' => 'queue'],
        ],
        'compliance' => [
            ['key' => 'schedule_call', 'label' => 'Schedule wellness call', 'default' => 'auto'],
            ['key' => 'file_form', 'label' => 'File compliance form', 'default' => 'auto'],
            ['key' => 'escalate_concern', 'label' => 'Escalate concern note', 'default' => 'queue'],
        ],
        'billing' => [
            ['key' => 'cp01_gate', 'label' => 'CP-01 pre-billing gate', 'default' => 'auto'],
            ['key' => 'generate_claim', 'label' => 'Generate 837P / invoice', 'default' => 'auto'],
            ['key' => 'submit_claim', 'label' => 'Submit to payer / ASW', 'default' => 'queue'],
            ['key' => 'resubmit_clean', 'label' => 'Auto-resubmit clean rejections', 'default' => 'auto'],
        ],
        'payroll' => [
            ['key' => 'build_batch', 'label' => 'Build payroll batch', 'default' => 'auto'],
            ['key' => 'release_batch', 'label' => 'Release to AccountantsWorld', 'default' => 'queue'],
        ],
        'background' => [
            ['key' => 'run_champs', 'label' => 'CHAMPS check', 'default' => 'auto'],
            ['key' => 'run_ichat', 'label' => 'ICHAT check', 'default' => 'auto'],
            ['key' => 'run_exclusions', 'label' => 'SAM / OIG batch', 'default' => 'auto'],
            ['key' => 'clear_flag', 'label' => 'Clear background flag', 'default' => 'queue'],
        ],
        'communications' => [
            ['key' => 'triage_inbound', 'label' => 'Triage inbound message', 'default' => 'auto'],
            ['key' => 'auto_reply', 'label' => 'Auto-reply (non-PHI)', 'default' => 'auto'],
            ['key' => 'escalate_phi', 'label' => 'Escalate PHI / complex', 'default' => 'queue'],
        ],
        'document' => [
            ['key' => 'classify', 'label' => 'Classify document', 'default' => 'auto'],
            ['key' => 'ocr_extract', 'label' => 'OCR / extract fields', 'default' => 'auto'],
            ['key' => 'archive', 'label' => 'Archive (7-yr retention)', 'default' => 'auto'],
            ['key' => 'suggest_exploration_views', 'label' => 'Suggest Data Exploration saved views', 'default' => 'auto'],
        ],
        'forms' => [
            ['key' => 'generate_draft', 'label' => 'Generate compliance draft', 'default' => 'auto'],
            ['key' => 'file_signed', 'label' => 'File signed PDF', 'default' => 'auto'],
        ],
        'evv' => [
            ['key' => 'flag_review', 'label' => 'Flag EVV issues for review', 'default' => 'auto'],
            ['key' => 'mark_missed', 'label' => 'Mark no-show visits', 'default' => 'auto'],
            ['key' => 'suggest_time_fix', 'label' => 'Suggest time correction', 'default' => 'queue'],
        ],
    ],

    'default_permissions' => [
        'intake' => ['view_clients', 'add_clients', 'edit_clients', 'view_dashboard'],
        'authorizations' => ['view_clients', 'edit_clients', 'view_dashboard'],
        'compliance' => ['view_clients', 'edit_clients', 'view_communications', 'view_dashboard'],
        'billing' => ['view_billing_claims_audit', 'edit_billing_claims_audit', 'run_billing', 'view_clients', 'view_dashboard'],
        'payroll' => ['view_payroll', 'edit_payroll', 'run_payroll', 'view_dashboard'],
        'background' => ['view_clients', 'edit_clients', 'view_dashboard'],
        'communications' => ['view_communications', 'send_communications', 'view_clients', 'view_dashboard'],
        'document' => ['view_clients', 'edit_clients', 'view_dashboard', 'view_data_exploration'],
        'forms' => ['view_forms', 'manage_forms', 'view_clients', 'view_dashboard'],
        'evv' => ['view_visit_reports', 'manage_visit_reports', 'view_tasks', 'view_dashboard'],
    ],

    /**
     * Catalog defaults for which vault credential keys an agent *typically* needs.
     * Informational only: used when seeding agents and rendering the Staff AI
     * Agents UI. Runtime integrations (e.g. DocuSign envelopes) resolve from the
     * organization Credential Vault → config('docusign.*'), not from these lists.
     */
    'default_credentials' => [
        'intake' => [IntegrationCredential::KEY_CHAMPS, IntegrationCredential::KEY_MDHHS],
        'authorizations' => [IntegrationCredential::KEY_MDHHS, IntegrationCredential::KEY_CHAMPS],
        'compliance' => [IntegrationCredential::KEY_GOOGLE_WORKSPACE, IntegrationCredential::KEY_DOCUSIGN],
        'billing' => [IntegrationCredential::KEY_AVAILITY, IntegrationCredential::KEY_SIGMA, IntegrationCredential::KEY_GOOGLE_WORKSPACE],
        'payroll' => [IntegrationCredential::KEY_ACCOUNTANTSWORLD, IntegrationCredential::KEY_HHA],
        'background' => [IntegrationCredential::KEY_CHAMPS, IntegrationCredential::KEY_ICHAT],
        'communications' => [IntegrationCredential::KEY_RINGCENTRAL, IntegrationCredential::KEY_GOOGLE_WORKSPACE],
        'document' => [IntegrationCredential::KEY_GOOGLE_WORKSPACE, IntegrationCredential::KEY_DOCUSIGN],
        'forms' => [IntegrationCredential::KEY_DOCUSIGN, IntegrationCredential::KEY_GOOGLE_WORKSPACE],
    ],

    'custom_templates' => [
        'blank' => [
            'name' => 'Custom agent',
            'icon' => '🤖',
            'icon_bg' => 'bg-[#dbeafe]',
            'role_description' => 'Custom automation agent',
            'autonomy_mode' => 'approval_required',
            'agent_actions' => [],
            'default_permissions' => ['view_dashboard'],
            'default_credentials' => [],
        ],
    ],

];

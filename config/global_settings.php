<?php

use App\Models\IntegrationCredential;

return [
    'integrations' => [
        [
            'slug' => 'accountantsworld',
            'name' => 'AccountantsWorld',
            'purpose' => 'Payroll',
            'method' => 'api',
            'credential_key' => IntegrationCredential::KEY_ACCOUNTANTSWORLD,
            'manage_tab' => 'credential-vault',
        ],
        [
            'slug' => 'hhaexchange',
            'name' => 'HHAeXchange',
            'purpose' => 'EVV (clock in/out)',
            'method' => 'api',
            'credential_key' => IntegrationCredential::KEY_HHA,
            'manage_tab' => 'credential-vault',
        ],
        [
            'slug' => 'ringcentral',
            'name' => 'RingCentral',
            'purpose' => 'Phone · SMS · eFax',
            'method' => 'api',
            'credential_key' => IntegrationCredential::KEY_RINGCENTRAL,
            'manage_tab' => 'credential-vault',
        ],
        [
            'slug' => 'availity',
            'name' => 'Availity',
            'purpose' => 'MICH 837P claims',
            'method' => 'api',
            'credential_key' => IntegrationCredential::KEY_AVAILITY,
            'manage_tab' => 'credential-vault',
        ],
        [
            'slug' => 'billing-claims',
            'name' => 'Billing & Claims Audit',
            'purpose' => '837P · DHS invoices · Sigma portal',
            'method' => 'api',
            'credential_key' => null,
            'manage_tab' => 'billing-claims',
        ],
        [
            'slug' => 'uhc-edi',
            'name' => 'UnitedHealthcare EDI',
            'purpose' => 'UHC 837P (separate)',
            'method' => 'edi',
            'credential_key' => null,
            'manage_tab' => 'billing-claims',
            'static_status' => 'Connected',
        ],
        [
            'slug' => 'google-workspace',
            'name' => 'Google Workspace',
            'purpose' => 'Email · calendar · drive',
            'method' => 'api',
            'credential_key' => IntegrationCredential::KEY_GOOGLE_WORKSPACE,
            'manage_tab' => 'credential-vault',
        ],
        [
            'slug' => 'docusign',
            'name' => 'DocuSign',
            'purpose' => 'E-signatures',
            'method' => 'api',
            'credential_key' => IntegrationCredential::KEY_DOCUSIGN,
            'manage_tab' => 'credential-vault',
        ],
        [
            'slug' => 'retell',
            'name' => 'Retell AI',
            'purpose' => 'Wellness & AI voice calls',
            'method' => 'api',
            'credential_key' => null,
            'manage_tab' => 'integrations',
            'static_status' => 'Configured via environment',
        ],
        // Client review D8: each state portal is its own manageable row with
        // Test / Manage, wired to the Credential Vault.
        [
            'slug' => IntegrationCredential::KEY_CHAMPS,
            'name' => 'CHAMPS / MILogin',
            'purpose' => 'Provider enrollment · eligibility',
            'method' => 'rpa',
            'credential_key' => IntegrationCredential::KEY_CHAMPS,
            'manage_tab' => 'credential-vault',
            'static_status' => 'Browser automation · creds in Vault',
        ],
        [
            'slug' => IntegrationCredential::KEY_MDHHS,
            'name' => 'MDHHS / Bridges',
            'purpose' => 'Intake · authorizations',
            'method' => 'rpa',
            'credential_key' => IntegrationCredential::KEY_MDHHS,
            'manage_tab' => 'credential-vault',
            'static_status' => 'Browser automation · creds in Vault',
        ],
        [
            'slug' => IntegrationCredential::KEY_SIGMA,
            'name' => 'Sigma Portal',
            'purpose' => 'DHS Home Help billing',
            'method' => 'rpa',
            'credential_key' => IntegrationCredential::KEY_SIGMA,
            'manage_tab' => 'credential-vault',
            'static_status' => 'Browser automation · creds in Vault',
        ],
        [
            'slug' => IntegrationCredential::KEY_ICHAT,
            'name' => 'ICHAT',
            'purpose' => 'Michigan criminal history checks',
            'method' => 'rpa',
            'credential_key' => IntegrationCredential::KEY_ICHAT,
            'manage_tab' => 'credential-vault',
            'static_status' => 'Browser automation · creds in Vault',
        ],
        [
            'slug' => 'sam-oig',
            'name' => 'SAM.gov · OIG LEIE',
            'purpose' => 'Exclusion checks',
            'method' => 'api_download',
            'credential_key' => null,
            'manage_tab' => 'integrations',
            'manage_route' => 'background-checks',
            'static_status' => 'Free · monthly batch',
        ],
    ],

    'vault_rpa' => [
        IntegrationCredential::KEY_CHAMPS => [
            'label' => 'CHAMPS / MILogin',
            'used_by' => 'Background-check · Billing',
            'portal_url' => 'https://milogin.michigan.gov',
            'test_method' => 'get',
        ],
        IntegrationCredential::KEY_SIGMA => [
            'label' => 'Sigma Portal',
            'used_by' => 'Billing agent',
            'portal_url' => 'https://www.michigan.gov/mdhhs',
            'test_method' => 'head',
        ],
        IntegrationCredential::KEY_ICHAT => [
            'label' => 'ICHAT',
            'used_by' => 'Background-check agent',
            'portal_url' => 'https://www.michigan.gov',
            'test_method' => 'head',
        ],
        IntegrationCredential::KEY_MDHHS => [
            'label' => 'MDHHS / Bridges',
            'used_by' => 'Intake · Authorizations',
            'portal_url' => 'https://www.michigan.gov/mdhhs',
            'test_method' => 'head',
        ],
    ],

    'exclusion_endpoints' => [
        ['name' => 'SAM.gov', 'url' => 'https://sam.gov', 'method' => 'head'],
        ['name' => 'OIG LEIE', 'url' => 'https://oig.hhs.gov/exclusions/exclusions_list.asp', 'method' => 'get'],
    ],

    'program_rules' => [
        [
            'program' => 'MICH',
            'badge' => 'blue',
            'compliance_basis' => 'Hours-based (authorized monthly hrs)',
            'auth_type' => 'Prior Authorization (PA)',
            'auth_expiry' => '~6 months · renew 2 wks prior',
            'payment' => 'EOB (Availity)',
        ],
        [
            'program' => 'DHS',
            'badge' => 'purple',
            'compliance_basis' => 'Days-based (required days/wk)',
            'auth_type' => 'Time/Task Sheet',
            'auth_expiry' => 'No expiry · 6-mo reassessment',
            'payment' => 'Sigma Portal',
        ],
    ],

    'us_states' => [
        'MI' => 'Michigan',
    ],

    'batch_build_days' => [
        'first_tuesday' => 'First Tuesday',
        'first_monday' => 'First Monday',
        'first_wednesday' => 'First Wednesday',
    ],

    'pay_days' => [
        'friday' => 'Friday',
        'following_friday' => 'Following Friday',
        'thursday' => 'Thursday',
    ],

    'employment_types' => [
        'w2' => 'W-2',
        '1099' => '1099',
    ],

    'autonomy_modes' => [
        'approval_required' => 'Approval-required',
        'autonomous' => 'Autonomous',
        'monitor' => 'Monitor',
    ],

    'signup_modes' => [
        'invite_only' => 'Invite-only',
        'open' => 'Open',
    ],

    'code_expiry_options' => [
        7 => '7 days',
        14 => '14 days',
        30 => '30 days',
    ],

    'session_timeout_options' => [
        15 => '15 minutes',
        30 => '30 minutes',
        60 => '60 minutes',
        120 => '120 minutes',
    ],

    'locked_rules' => [
        ['key' => 'cp01_pre_billing_gate', 'label' => 'CP-01 pre-billing gate'],
        ['key' => 'pay_grace_window', 'label' => '~10-day pay grace window'],
        ['key' => 'stop_on_expired_pa', 'label' => 'Stop service on expired PA / no renewal'],
        ['key' => 'verify_background_check', 'label' => 'Flagged background check → verify, never auto-disqualify'],
        ['key' => 'backdating_family_only', 'label' => 'Backdating: family only', 'hint' => 'agency-sourced cannot backdate'],
    ],

    'supported_languages' => [
        'en' => ['label' => 'English', 'badge' => 'blue'],
        'ar' => ['label' => 'العربية Arabic', 'badge' => 'purple'],
    ],
];

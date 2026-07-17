<?php

use App\Models\IntegrationCredential;

return [
    /*
    |--------------------------------------------------------------------------
    | Vendor integration catalog (Directory cards → Credential Vault → app tabs)
    |--------------------------------------------------------------------------
    */
    'vendors' => [
        'accountantsworld' => [
            'label' => 'AccountantsWorld (Payroll)',
            'credential_key' => IntegrationCredential::KEY_ACCOUNTANTSWORLD,
            'data_flow' => 'Verified hours out → batch built → pay stubs + tax back (bi-directional)',
            'app_area' => 'payroll',
            'app_route' => 'payroll',
            'app_label' => 'Payroll tab',
            'owning_agent' => 'Payroll agent',
        ],
        'hhaexchange' => [
            'label' => 'HHAeXchange (EVV)',
            'credential_key' => IntegrationCredential::KEY_HHA,
            'data_flow' => 'Caregiver clock in/out, visit verification',
            'app_area' => 'calendar',
            'app_route' => 'calendar',
            'app_label' => 'Calendar / Visit Reports',
            'owning_agent' => 'EVV / Visits agent',
        ],
        'ringcentral' => [
            'label' => 'RingCentral (Phone/eFax)',
            'credential_key' => IntegrationCredential::KEY_RINGCENTRAL,
            'data_flow' => 'Calls, SMS, voicemail, eFax in/out',
            'app_area' => 'communications',
            'app_route' => 'communications.index',
            'app_label' => 'Communications tab',
            'owning_agent' => 'Receptionist / Comms agent',
        ],
        'availity_claims' => [
            'label' => 'Availity (Claims clearinghouse)',
            'credential_key' => IntegrationCredential::KEY_AVAILITY,
            'data_flow' => '837 claim out / 835 remittance back (MCO claims)',
            'app_area' => 'billing',
            'app_route' => 'billing-claims-audit.index',
            'app_label' => 'Billing & Claims',
            'owning_agent' => 'Billing agent',
        ],
        'mdhhs_sigma' => [
            'label' => 'MDHHS / Sigma Portal (State billing)',
            'credential_key' => IntegrationCredential::KEY_SIGMA,
            'data_flow' => 'DHS Home Help invoices / time sheets',
            'app_area' => 'billing',
            'app_route' => 'billing-claims-audit.index',
            'app_label' => 'Billing & Claims (DHS channel)',
            'owning_agent' => 'Billing agent',
        ],
        'champs_eligibility' => [
            'label' => 'CHAMPS / Availity (Eligibility)',
            'credential_key' => IntegrationCredential::KEY_CHAMPS,
            'data_flow' => 'Eligibility check result',
            'app_area' => 'intake',
            'app_route' => 'clients.index',
            'app_label' => 'Client → Eligibility',
            'owning_agent' => 'Intake agent',
        ],
        'docusign' => [
            'label' => 'DocuSign (e-Sign)',
            'credential_key' => IntegrationCredential::KEY_DOCUSIGN,
            'data_flow' => 'Signature request / signed back',
            'app_area' => 'compliance',
            'app_route' => 'compliance',
            'app_label' => 'Compliance Forms',
            'owning_agent' => 'Compliance agent',
        ],
        'google_workspace' => [
            'label' => 'Google Workspace (Email)',
            'credential_key' => IntegrationCredential::KEY_GOOGLE_WORKSPACE,
            'data_flow' => 'Outbound / inbound email',
            'app_area' => 'communications',
            'app_route' => 'communications.index',
            'app_label' => 'Communications',
            'owning_agent' => 'Receptionist / Comms agent',
        ],
        'retell' => [
            'label' => 'Retell (Voice AI)',
            'credential_key' => null,
            'data_flow' => 'Wellness calls, voicemail transcripts, concern routing',
            'app_area' => 'communications',
            'app_route' => 'communications.index',
            'app_label' => 'Communications',
            'owning_agent' => 'Receptionist / Comms agent',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | State / portal systems (reference + RPA, linked to vault credentials)
    |--------------------------------------------------------------------------
    */
    'systems' => [
        'champs' => [
            'label' => 'CHAMPS',
            'credential_key' => IntegrationCredential::KEY_CHAMPS,
            'owning_agent' => 'Intake agent',
        ],
        'mdhhs' => [
            'label' => 'MDHHS Portal',
            'credential_key' => IntegrationCredential::KEY_MDHHS,
            'owning_agent' => 'Billing agent',
        ],
        'sigma' => [
            'label' => 'Sigma (DHS)',
            'credential_key' => IntegrationCredential::KEY_SIGMA,
            'owning_agent' => 'Billing agent',
        ],
        'ichat' => [
            'label' => 'iChat',
            'credential_key' => IntegrationCredential::KEY_ICHAT,
            'owning_agent' => 'Intake agent',
        ],
    ],
];

<?php

return [

    /*
    | When no live approval items exist in the database, show the curated demo
    | cards below so the queue UI matches the product spec during development.
    */
    'demo_fallback' => env('WORKFLOW_QUEUES_DEMO', true),

    'sla_hours' => 24,

    'miss_rate_threshold' => 2.0,

    /*
    | Owner Approval Queue cards shown per page (initial load + each "Load more").
    */
    'approvals_per_page' => (int) env('WORKFLOW_QUEUES_APPROVALS_PER_PAGE', 12),

    'demo_approvals' => [
        [
            'slug' => 'demo-oig-mahmoud',
            'category' => 'Background check · verify before disqualify',
            'title' => 'OIG flag — Mahmoud Ghazawai (caregiver)',
            'urgent' => true,
            'sla' => ['label' => 'Due in 3h', 'tone' => 'now'],
            'context' => [
                ['label' => 'Caregiver', 'value' => 'Mahmoud Ghazawai · serves George Papadopoulos'],
                ['label' => 'Match', 'value' => 'Possible OIG LEIE name match'],
                ['label' => 'Status now', 'value' => 'badge:red:On Hold'],
                ['label' => 'Address on file', 'value' => 'Differs from OIG record'],
            ],
            'reason' => 'Likely a false match (common name; address differs). Verify same-person by address/DOB. If not him → clear & resume; if confirmed → terminate & replace. No billing while on hold.',
            'reason_tone' => 'warn',
            'actions' => [
                ['label' => '✓ Verified — clear', 'action' => 'approve', 'variant' => 'success'],
                ['label' => 'Confirm match — disqualify', 'action' => 'reject', 'variant' => 'danger'],
                ['label' => 'Hold', 'action' => 'hold', 'variant' => 'secondary'],
            ],
            'review_label' => 'Open caregiver →',
            'review_url' => null,
        ],
        [
            'slug' => 'demo-pa-maria',
            'category' => 'Authorization renewal · send to MCO',
            'title' => 'Renew Prior Authorization — Maria Hassan',
            'urgent' => false,
            'sla' => ['label' => 'Due today', 'tone' => 'now'],
            'context' => [
                ['label' => 'Program · MCO', 'value' => 'MICH · Aetna (Availity)'],
                ['label' => 'Current PA', 'value' => 'Expires Jun 14 (14 days)'],
                ['label' => 'Service', 'value' => 'T1019 · 120 hrs/mo'],
                ['label' => 'Packet', 'value' => 'Prepared by agent · eligibility re-verified'],
            ],
            'reason' => 'Renewal request is due 2 weeks before PA end. Approve to submit the prepared packet to Aetna. If it lapses, service stops.',
            'reason_tone' => 'info',
            'actions' => [
                ['label' => '✓ Approve & send', 'action' => 'approve', 'variant' => 'success'],
                ['label' => 'Hold', 'action' => 'hold', 'variant' => 'secondary'],
            ],
            'review_label' => 'View authorization →',
            'review_url' => null,
        ],
        [
            'slug' => 'demo-activate-layla',
            'category' => 'Activate · caregiver',
            'title' => 'Approve caregiver — Layla Ahmed',
            'urgent' => false,
            'sla' => ['label' => 'Due in 8h', 'tone' => 'soon'],
            'context' => [
                ['label' => 'Serves', 'value' => 'Khalil Ahmed (husband · DHS)'],
                ['label' => 'CHAMPS', 'value' => 'badge:green:Approved & associated'],
                ['label' => 'Checks', 'value' => 'ICHAT/SAM/OIG clear'],
                ['label' => 'Live-in', 'value' => 'BPHASA-2421 approved · EVV exempt'],
            ],
            'reason' => 'All onboarding gates passed. Approve to activate Layla and link her to Khalil\'s chart.',
            'reason_tone' => 'info',
            'actions' => [
                ['label' => '✓ Activate', 'action' => 'approve', 'variant' => 'success'],
                ['label' => 'Hold', 'action' => 'hold', 'variant' => 'secondary'],
                ['label' => 'Reject', 'action' => 'reject', 'variant' => 'danger'],
            ],
            'review_label' => 'Open caregiver →',
            'review_url' => null,
        ],
        [
            'slug' => 'demo-billing-hisham',
            'category' => 'Pre-billing gate · hold',
            'title' => 'Billing held — Hisham Khan',
            'urgent' => false,
            'sla' => ['label' => 'Due in 10h', 'tone' => 'soon'],
            'context' => [
                ['label' => 'Program', 'value' => 'MICH · Humana'],
                ['label' => 'Gate fail', 'value' => 'Prior claim unpaid + PA expired 3d'],
                ['label' => 'Status', 'value' => 'badge:red:On Hold'],
                ['label' => 'Agent finding', 'value' => 'Possible silent case closure'],
            ],
            'reason' => 'This month\'s bill is held — last claim unpaid and PA expired. Investigate before billing (don\'t bill on an expired auth). Approve = hold confirmed / route to renewal.',
            'reason_tone' => 'warn',
            'actions' => [
                ['label' => 'Keep on hold', 'action' => 'hold', 'variant' => 'secondary'],
                ['label' => 'Start renewal', 'action' => 'approve', 'variant' => 'secondary'],
            ],
            'review_label' => 'Open client →',
            'review_url' => null,
        ],
        [
            'slug' => 'demo-denied-eleanor',
            'category' => 'Denied case · escalation',
            'title' => 'DHS denied — Eleanor Morrison',
            'urgent' => false,
            'sla' => ['label' => 'Due in 20h', 'tone' => 'ok'],
            'context' => [
                ['label' => 'Program', 'value' => 'DHS Home Help'],
                ['label' => 'Reason (AI triage)', 'value' => 'Missing MDHHS-6200 (physician)'],
                ['label' => 'Fixable?', 'value' => 'Yes — resubmit with 6200'],
                ['label' => 'Next step', 'value' => 'Agent faxed PCP for 6200'],
            ],
            'reason' => 'AI triaged the denial: the medical-needs form wasn\'t on file. Approve to resubmit once the 6200 returns.',
            'reason_tone' => 'info',
            'actions' => [
                ['label' => '✓ Approve resubmit', 'action' => 'approve', 'variant' => 'success'],
                ['label' => 'Hold', 'action' => 'hold', 'variant' => 'secondary'],
            ],
            'review_label' => 'Open client →',
            'review_url' => null,
        ],
        [
            'slug' => 'demo-activate-khalil',
            'category' => 'Activate · new client',
            'title' => 'Activate client — Khalil Ahmed (DHS)',
            'urgent' => false,
            'sla' => ['label' => 'Due in 18h', 'tone' => 'soon'],
            'context' => [
                ['label' => 'Program', 'value' => 'DHS Home Help'],
                ['label' => 'Determination', 'value' => 'badge:green:Approved · Time/Task received'],
                ['label' => 'Caregiver', 'value' => 'Layla Ahmed (wife · live-in) — approved'],
                ['label' => 'Docs / eligibility', 'value' => 'Packet complete · CHAMPS eligible'],
            ],
            'reason' => 'Chart was created at intake as Pending Application. DHS determination is back and everything\'s in place — approve to flip the client to Active and start service.',
            'reason_tone' => 'info',
            'actions' => [
                ['label' => '✓ Activate client', 'action' => 'approve', 'variant' => 'success'],
                ['label' => 'Hold', 'action' => 'hold', 'variant' => 'secondary'],
                ['label' => 'Reject', 'action' => 'reject', 'variant' => 'danger'],
            ],
            'review_label' => 'Open client →',
            'review_url' => null,
        ],
        [
            'slug' => 'demo-discharge-helen',
            'category' => 'Discharge · client death',
            'title' => 'Confirm discharge — Helen Brooks (deceased)',
            'urgent' => true,
            'sla' => ['label' => 'Due in 6h', 'tone' => 'now'],
            'context' => [
                ['label' => 'Reported', 'value' => 'By family · May 28, 2026'],
                ['label' => 'Program', 'value' => 'MICH · Molina'],
                ['label' => 'Billing', 'value' => 'Prorate to date of death · no further claims'],
                ['label' => 'Compliance', 'value' => 'No form accepted for remaining days'],
            ],
            'reason' => 'Per policy: client death → immediate discharge, services stop, no further billing, no compliance form accepted. Confirm to discharge the client, close the caregiver assignment, and lock billing.',
            'reason_tone' => 'warn',
            'actions' => [
                ['label' => 'Confirm discharge', 'action' => 'approve', 'variant' => 'danger'],
                ['label' => 'Hold — verify first', 'action' => 'hold', 'variant' => 'secondary'],
            ],
            'review_label' => 'Open client →',
            'review_url' => null,
        ],
    ],

    'demo_human_tasks' => [
        [
            'slug' => 'demo-task-khalil-id',
            'title' => 'Verify photo ID & fax DHS packet — Khalil Ahmed',
            'description' => 'MSA-4676 needs in-person photo-ID verification before the agent can fax the packet to DHS.',
            'assignee' => 'Front desk',
            'due' => 'Due today',
            'due_tone' => 'urgent',
        ],
        [
            'slug' => 'demo-task-fatima-call',
            'title' => 'Call Aetna Case Coordinator — Fatima Al-Sayed',
            'description' => 'AI Secretary couldn\'t reach the coordinator after 2 attempts; needs a human callback re: PA renewal.',
            'assignee' => 'Anyone',
            'due' => 'Due tomorrow',
            'due_tone' => 'normal',
        ],
        [
            'slug' => 'demo-task-dolores-signature',
            'title' => 'Collect wet signature — Dolores Brown',
            'description' => 'Client must sign the updated agreement in person; scan back to the chart.',
            'assignee' => 'Field visit',
            'due' => 'Due Fri',
            'due_tone' => 'normal',
        ],
    ],

    'demo_exceptions' => [
        [
            'slug' => 'demo-fax-samuel',
            'icon' => '⚠️',
            'title' => 'Inbound Fax Parser — low confidence (88%)',
            'description' => 'PA letter for Samuel Okafor — 2 fields unclear (units, end date). Verify before it posts to the chart.',
            'link_label' => 'Review fields',
            'link_url' => null,
        ],
    ],

];

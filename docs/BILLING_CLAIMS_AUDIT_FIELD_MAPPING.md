# Billing & Claims Audit — Figma-to-Database Field Mapping

| Figma UI Element | Database Field | Type | Notes |
|------------------|----------------|------|-------|
| Client name / avatar | `client_id` | FK → `clients` | Join for display name |
| Caregiver name | `employee_id` | FK → `employees` | Nullable; relationship in `caregiver_relationship` |
| Program badge (MICH / DHS) | `program_type` | string(20) | Enum: MICH, DHS; filterable |
| Period (May 2024) | `billing_period` | date | First day of billing month; period selector |
| Service period range | `period_start`, `period_end` | date | Detail view header |
| Hours column | `total_hours` | decimal(8,2) | MICH hours-based billing |
| Days column | `total_days`, `days_required_per_week`, `days_met_status` | int / string | DHS days-based billing |
| Service code (T019) | `service_code` | string(20) | Line item code |
| Units (432 units) | `units` | unsigned int | MICH unit count |
| Service description | `service_description` | string | Line item / DHS service row |
| Rate column | `hourly_rate` | decimal(8,2) | Editable on detail page |
| Amount column | `total_amount` | decimal(12,2) | Server-calculated from hours × rate |
| Paid amount (EOB) | `paid_amount` | decimal(12,2) | Nullable; paid claims |
| Channel (837P - Availity) | `submission_channel` | string | Searchable display |
| Channel subtext (MCO) | `channel_subtext` | string | Nullable |
| Payer type | `payer_type` | string | e.g. MCO, MDHHS |
| Health plan | `health_plan_name` | string | MICH detail |
| Medicaid ID (masked) | `medicaid_id` | string | Masked in Blade |
| Plan member ID | `plan_member_id` | string | MICH detail |
| Authorization # | `authorization_number` | string | PA reference |
| Authorization expiry | `authorization_valid_through` | date | Nullable |
| DHS authorization text | `authorization_description` | string | e.g. Time/Task Sheet |
| Authorizing worker | `authorizing_worker_name` | string | DHS ASW |
| Caregiver relationship | `caregiver_relationship` | string | e.g. live-in spouse |
| EVV exempt flag | `evv_exempt` | boolean | Detail badge |
| Status badge | `claim_status` | string(30) | submitted, on_hold, awaiting_payment, paid, rejected |
| Status detail (Paid - EOB) | `status_detail` | string | Display label override |
| CP-01 hold | `hold_reason` | string | on_hold records; banner count |
| Rejection reason | `rejection_reason` | text | Rejected workflow |
| Audit status | `audit_status` | string(30) | Controlled enum; backend validation |
| Notes | `notes` | text | Max 5000 chars |
| Claim # / Invoice # | `claim_number` | string | Unique per org; searchable |
| Submitted date | `submitted_at` | timestamp | Aging calculation |
| Paid date | `paid_at` | timestamp | Lifecycle |
| View PDF action | `pdf_path` | string | Document storage path |
| Payment lifecycle timeline | `lifecycle_events` | JSON | Array of step objects |
| Documents list | `documents` | JSON | Array of name/path/status |
| Summary: Billed this cycle | `total_amount` | aggregate | Sum for selected `billing_period` |
| Summary: Paid / confirmed | `claim_status = paid` | aggregate | Sum `total_amount` or `paid_amount` |
| Summary: Awaiting payment | `claim_status = awaiting_payment` | aggregate | |
| Summary: On hold (CP-01) | `claim_status = on_hold` | count | |
| Summary: Rejected / rework | `claim_status = rejected` | count | |
| Aging bucket (0-30d, etc.) | computed from `submitted_at` | — | Not stored; computed at query time |
| Created / updated audit | `created_by`, `updated_by` | FK → `users` | |
| Timestamps | `created_at`, `updated_at` | timestamp | |
| Soft delete | `deleted_at` | timestamp | SoftDeletes |

## Assumptions

- `billing_period` stores the first day of the month shown in the Figma period selector.
- DHS invoices use `claim_number` with `HH-` prefix; MICH uses `837P-` prefix.
- Auto-billing and CP-01 gate banner counts are computed from records in the selected period, not stored separately.
- Aging report uses `submitted_at` vs snapshot date; only outstanding (`submitted`, `awaiting_payment`) claims appear in aging buckets.

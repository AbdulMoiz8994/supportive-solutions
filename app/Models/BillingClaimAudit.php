<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingClaimAudit extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    public const PROGRAM_MICH = 'MICH';

    public const PROGRAM_DHS = 'DHS';

    // Legacy claim_status (Figma display)
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';

    // Billing workflow statuses
    public const BILLING_NOT_READY = 'not_ready';
    public const BILLING_READY = 'ready_to_bill';
    public const BILLING_BLOCKED = 'blocked';
    public const BILLING_DRAFT = 'draft';
    public const BILLING_SENT = 'sent';
    public const BILLING_SUBMITTED = 'submitted';
    public const BILLING_PENDING_PAYMENT = 'pending_payment';
    public const BILLING_PAID = 'paid';
    public const BILLING_PARTIALLY_PAID = 'partially_paid';
    public const BILLING_DENIED = 'denied';
    public const BILLING_UNDERPAID = 'underpaid';
    public const BILLING_VOIDED = 'voided';
    public const BILLING_NEEDS_REVIEW = 'needs_review';

    public const AUTH_STATUS_ACTIVE = 'active';
    public const AUTH_STATUS_EXPIRING_SOON = 'expiring_soon';
    public const AUTH_STATUS_EXPIRED = 'expired';
    public const AUTH_STATUS_MISSING = 'missing';
    public const AUTH_STATUS_NEEDS_REVIEW = 'needs_review';

    public const EVV_VERIFIED = 'verified';
    public const EVV_VERIFIED_LOCAL = 'verified_local';
    public const EVV_PENDING = 'pending';
    public const EVV_PENDING_SYNC = 'pending_sync';
    public const EVV_MISSING = 'missing';
    public const EVV_EXEMPT = 'exempt';
    public const EVV_NOT_CONNECTED = 'not_connected';

    public const VISIT_VERIFIED = 'verified';
    public const VISIT_PENDING = 'pending';
    public const VISIT_MISSING = 'missing';
    public const VISIT_PARTIAL = 'partial';

    public const PAYMENT_PAID_FULL = 'paid_full';
    public const PAYMENT_PARTIAL = 'partial';
    public const PAYMENT_UNDERPAID = 'underpaid';
    public const PAYMENT_OVERPAID = 'overpaid';
    public const PAYMENT_DENIED = 'denied';
    public const PAYMENT_MISSING_EOB = 'missing_eob';
    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_NOT_RECEIVED = 'not_received';

    public const AI_NOT_CONNECTED = 'not_connected';
    public const AI_PENDING_MANUAL = 'pending_manual_review';
    public const AI_EXTRACTED = 'extracted';
    public const AI_FAILED = 'failed';

    public const AUDIT_NOT_REVIEWED = 'not_reviewed';
    public const AUDIT_PASSED = 'passed';
    public const AUDIT_IN_REVIEW = 'in_review';
    public const AUDIT_ISSUE_FOUND = 'issue_found';
    public const AUDIT_NEEDS_STAFF_REVIEW = 'needs_staff_review';
    public const AUDIT_RESOLVED = 'resolved';
    public const AUDIT_ESCALATED = 'escalated';

    protected $fillable = [
        'organization_id', 'client_id', 'employee_id', 'care_detail_id',
        'claim_number', 'invoice_number', 'program_type', 'coverage_type',
        'billing_period', 'period_start', 'period_end',
        'total_hours', 'scheduled_hours', 'verified_hours', 'completed_visit_hours',
        'total_days', 'days_required_per_week', 'days_met_status',
        'service_code', 'service_description', 'units', 'unit_minutes',
        'approved_monthly_hours', 'approved_weekly_hours', 'calculated_daily_hours', 'calculated_approved_hours',
        'hourly_rate', 'total_amount', 'expected_amount', 'paid_amount', 'balance_amount',
        'adjustment_amount', 'denial_amount',
        'submission_channel', 'billing_method', 'billing_route', 'channel_subtext',
        'payer_type', 'payer_name', 'health_plan_name', 'medicaid_id', 'plan_member_id',
        'authorization_number', 'authorization_start_date', 'authorization_valid_through',
        'authorization_status', 'authorization_document_path', 'authorization_description', 'authorizing_worker_name',
        'caregiver_relationship', 'evv_exempt', 'evv_status', 'visit_verification_status',
        'clock_in_verified', 'clock_out_verified',
        'claim_status', 'billing_status', 'status_detail', 'hold_reason',
        'payment_status', 'audit_status', 'rejection_reason', 'adjustment_reason', 'notes',
        'submitted_at', 'paid_at', 'payment_date', 'payer_reference',
        'availity_reference_id', 'availity_status', 'availity_status_payload', 'availity_status_checked_at',
        'pdf_path', 'eob_document_path', 'lifecycle_events', 'documents',
        'ai_extraction_status', 'ai_extracted_amount', 'ai_extracted_confidence', 'ai_review_required', 'ai_notes',
        'override_reason', 'overridden_by', 'overridden_at',
        'issue_flags', 'last_action', 'activity_log',
        'created_by', 'updated_by',
        'eligibility_verified_at', 'eligibility_verified_by', 'eligibility_note', 'submitted_by',
    ];

    protected $casts = [
        'billing_period' => 'date', 'period_start' => 'date', 'period_end' => 'date',
        'authorization_start_date' => 'date', 'authorization_valid_through' => 'date', 'payment_date' => 'date',
        'total_hours' => 'decimal:2', 'scheduled_hours' => 'decimal:2', 'verified_hours' => 'decimal:2',
        'completed_visit_hours' => 'decimal:2', 'approved_monthly_hours' => 'decimal:2', 'approved_weekly_hours' => 'decimal:2',
        'calculated_daily_hours' => 'decimal:2', 'calculated_approved_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2', 'total_amount' => 'decimal:2', 'expected_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2', 'balance_amount' => 'decimal:2', 'adjustment_amount' => 'decimal:2', 'denial_amount' => 'decimal:2',
        'ai_extracted_amount' => 'decimal:2', 'ai_extracted_confidence' => 'decimal:2',
        'evv_exempt' => 'boolean', 'clock_in_verified' => 'boolean', 'clock_out_verified' => 'boolean', 'ai_review_required' => 'boolean',
        'submitted_at' => 'datetime', 'paid_at' => 'datetime', 'overridden_at' => 'datetime',
        'eligibility_verified_at' => 'datetime',
        'availity_status_payload' => 'array', 'availity_status_checked_at' => 'datetime',
        'lifecycle_events' => 'array', 'documents' => 'array', 'issue_flags' => 'array', 'activity_log' => 'array',
    ];

    public static function programTypes(): array
    {
        return [self::PROGRAM_MICH, self::PROGRAM_DHS];
    }

    public static function claimStatuses(): array
    {
        return [
            self::STATUS_SUBMITTED,
            self::STATUS_ON_HOLD,
            self::STATUS_AWAITING_PAYMENT,
            self::STATUS_PAID,
            self::STATUS_REJECTED,
        ];
    }

    public static function billingStatuses(): array
    {
        return config('billing_claims_audit.billing_statuses') ?? [
            self::BILLING_NOT_READY, self::BILLING_READY, self::BILLING_BLOCKED, self::BILLING_DRAFT,
            self::BILLING_SENT, self::BILLING_SUBMITTED, self::BILLING_PENDING_PAYMENT, self::BILLING_PAID,
            self::BILLING_PARTIALLY_PAID, self::BILLING_DENIED, self::BILLING_UNDERPAID, self::BILLING_VOIDED,
            self::BILLING_NEEDS_REVIEW,
        ];
    }

    public static function auditStatuses(): array
    {
        return config('billing_claims_audit.audit_statuses') ?? [
            self::AUDIT_NOT_REVIEWED,
            self::AUDIT_IN_REVIEW,
            self::AUDIT_PASSED,
            self::AUDIT_ISSUE_FOUND,
            self::AUDIT_NEEDS_STAFF_REVIEW,
            self::AUDIT_RESOLVED,
            self::AUDIT_ESCALATED,
        ];
    }

    public static function authorizationStatuses(): array
    {
        return config('billing_claims_audit.authorization_statuses') ?? [
            self::AUTH_STATUS_ACTIVE, self::AUTH_STATUS_EXPIRING_SOON, self::AUTH_STATUS_EXPIRED,
            self::AUTH_STATUS_MISSING, self::AUTH_STATUS_NEEDS_REVIEW,
        ];
    }

    public static function paymentStatuses(): array
    {
        return config('billing_claims_audit.payment_statuses') ?? [
            self::PAYMENT_PAID_FULL, self::PAYMENT_PARTIAL, self::PAYMENT_UNDERPAID, self::PAYMENT_OVERPAID,
            self::PAYMENT_DENIED, self::PAYMENT_MISSING_EOB, self::PAYMENT_PENDING, self::PAYMENT_NOT_RECEIVED,
        ];
    }

    public function client() { return $this->belongsTo(Client::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function careDetail() { return $this->belongsTo(CareDetail::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function overrider() { return $this->belongsTo(User::class, 'overridden_by'); }
    public function eligibilityVerifier() { return $this->belongsTo(User::class, 'eligibility_verified_by'); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }

    public function isMich(): bool { return $this->program_type === self::PROGRAM_MICH; }
    public function isDhs(): bool { return $this->program_type === self::PROGRAM_DHS; }

    // ── Manual billing flow (dropped-API replacement) ──────────────────────────

    /** Eligibility confirmed active in the payer portal (its own step, pre-submit). */
    public function isEligibilityVerified(): bool
    {
        return $this->eligibility_verified_at !== null;
    }

    /** CP-01 pre-billing gate is holding this claim (must be overridden first). */
    public function isCpBlocked(): bool
    {
        return in_array($this->billing_status, [self::BILLING_BLOCKED, self::BILLING_NOT_READY], true)
            || $this->claim_status === self::STATUS_ON_HOLD;
    }

    /** Claim has been submitted (or is further along: awaiting/paid/denied). */
    public function isSubmittedOrBeyond(): bool
    {
        return in_array($this->billing_status, [
            self::BILLING_SENT, self::BILLING_SUBMITTED, self::BILLING_PENDING_PAYMENT,
            self::BILLING_PAID, self::BILLING_PARTIALLY_PAID, self::BILLING_DENIED,
            self::BILLING_UNDERPAID, self::BILLING_VOIDED,
        ], true) || in_array($this->claim_status, [
            self::STATUS_SUBMITTED, self::STATUS_AWAITING_PAYMENT, self::STATUS_PAID, self::STATUS_REJECTED,
        ], true) || $this->submitted_at !== null;
    }

    /** Pre-submission and not CP-01 blocked → the manual eligibility/submit steps apply. */
    public function isAwaitingManualSubmission(): bool
    {
        return ! $this->isSubmittedOrBeyond() && ! $this->isCpBlocked();
    }

    /** Payment resolved — paid/confirmed or denied (no further EOB step). */
    public function isPaidOrDenied(): bool
    {
        return in_array($this->billing_status, [
            self::BILLING_PAID, self::BILLING_PARTIALLY_PAID, self::BILLING_DENIED,
        ], true) || in_array($this->claim_status, [self::STATUS_PAID, self::STATUS_REJECTED], true);
    }

    /** Submitted and awaiting payment resolution → the "Record EOB" step applies. */
    public function isAwaitingPaymentResolution(): bool
    {
        return $this->isSubmittedOrBeyond() && ! $this->isPaidOrDenied();
    }

    public function isOutstanding(): bool
    {
        return in_array($this->billing_status ?? $this->claim_status, [
            self::BILLING_SUBMITTED, self::BILLING_SENT, self::BILLING_PENDING_PAYMENT,
            self::STATUS_SUBMITTED, self::STATUS_AWAITING_PAYMENT,
        ], true);
    }

    public function ageInDays(?\Carbon\Carbon $asOf = null): ?int
    {
        if (! $this->submitted_at) return null;
        return (int) $this->submitted_at->startOfDay()->diffInDays(($asOf ?? now())->startOfDay());
    }

    public function agingBucket(?\Carbon\Carbon $asOf = null): ?string
    {
        $age = $this->ageInDays($asOf);
        if ($age === null) return null;
        return match (true) {
            $age <= 30 => 'current', $age <= 60 => '31_60', $age <= 90 => '61_90', default => '90_plus',
        };
    }

    public function maskedMedicaidId(): string
    {
        if (! $this->medicaid_id) return '—';
        return '**** *** '.substr(preg_replace('/\D/', '', $this->medicaid_id), -4);
    }

    public function statusBadgeVariant(): string
    {
        $status = $this->billing_status ?? $this->claim_status;
        return match ($status) {
            self::BILLING_PAID, self::STATUS_PAID => 'green',
            self::BILLING_SUBMITTED, self::BILLING_SENT, self::STATUS_SUBMITTED => 'blue',
            self::BILLING_PENDING_PAYMENT, self::STATUS_AWAITING_PAYMENT => 'indigo',
            self::BILLING_BLOCKED, self::BILLING_NOT_READY, self::STATUS_ON_HOLD => 'amber',
            self::BILLING_DENIED, self::BILLING_UNDERPAID, self::STATUS_REJECTED => 'red',
            self::BILLING_READY => 'green',
            default => 'gray',
        };
    }

    public function statusLabel(): string
    {
        $label = $this->status_detail ?? ucwords(str_replace('_', ' ', $this->billing_status ?? $this->claim_status));
        return str_replace([' - ', ' – '], ' · ', $label);
    }

    public function healthPlanShortName(): ?string
    {
        return $this->health_plan_name ? explode(' ', trim($this->health_plan_name))[0] : null;
    }

    public function channelDisplaySubtext(): ?string
    {
        return $this->isMich() ? ($this->healthPlanShortName() ?? $this->channel_subtext) : $this->channel_subtext;
    }

    public function overdueActionLabel(?\Carbon\Carbon $asOf = null): string
    {
        if ($this->isDhs()) return 'Re-send to ASW';
        return ($this->ageInDays($asOf) ?? 0) >= 60 && $this->audit_status !== self::AUDIT_ESCALATED ? 'Escalate to AI' : 'View claim';
    }

    public static function formatYtdAmount(float $amount): string
    {
        if ($amount >= 1_000_000) return '$'.number_format($amount / 1_000_000, 2).'M';
        if ($amount >= 1_000) return '$'.number_format($amount / 1_000, 0).'K';
        return '$'.number_format($amount, 0);
    }

    public function recalculateTotalAmount(): void
    {
        if ($this->total_hours !== null) {
            $this->total_amount = round((float) $this->total_hours * (float) $this->hourly_rate, 2);
        }
    }

    public function syncClaimStatusFromBillingStatus(): void
    {
        $this->claim_status = $this->claimStatusFromBillingStatus($this->billing_status)
            ?? $this->claim_status
            ?? self::STATUS_SUBMITTED;
    }

    public function effectiveClaimStatus(): string
    {
        if (
            filled($this->claim_status)
            && in_array($this->claim_status, self::claimStatuses(), true)
        ) {
            return $this->claim_status;
        }

        return $this->claimStatusFromBillingStatus($this->billing_status)
            ?? self::STATUS_SUBMITTED;
    }

    /**
     * @return list<string>
     */
    public static function billingStatusesForClaimStatus(string $claimStatus): array
    {
        return match ($claimStatus) {
            self::STATUS_PAID => [self::BILLING_PAID, self::BILLING_PARTIALLY_PAID],
            self::STATUS_SUBMITTED => [self::BILLING_SUBMITTED, self::BILLING_SENT],
            self::STATUS_AWAITING_PAYMENT => [self::BILLING_PENDING_PAYMENT, self::BILLING_UNDERPAID],
            self::STATUS_REJECTED => [self::BILLING_DENIED],
            self::STATUS_ON_HOLD => [
                self::BILLING_BLOCKED,
                self::BILLING_NOT_READY,
                self::BILLING_NEEDS_REVIEW,
                self::BILLING_VOIDED,
            ],
            default => [],
        };
    }

    protected function claimStatusFromBillingStatus(?string $billingStatus): ?string
    {
        return match ($billingStatus) {
            self::BILLING_PAID, self::BILLING_PARTIALLY_PAID => self::STATUS_PAID,
            self::BILLING_SUBMITTED, self::BILLING_SENT => self::STATUS_SUBMITTED,
            self::BILLING_PENDING_PAYMENT, self::BILLING_UNDERPAID => self::STATUS_AWAITING_PAYMENT,
            self::BILLING_DENIED => self::STATUS_REJECTED,
            self::BILLING_BLOCKED, self::BILLING_NOT_READY, self::BILLING_NEEDS_REVIEW, self::BILLING_VOIDED => self::STATUS_ON_HOLD,
            default => null,
        };
    }

    public function hasIssueFlags(): bool
    {
        return ! empty($this->issue_flags);
    }

    public function issueFlagLabels(): array
    {
        return collect($this->issue_flags ?? [])->map(fn ($f) => ucwords(str_replace('_', ' ', $f)))->all();
    }

    public function isBillingReady(): bool
    {
        return in_array($this->billing_status, [self::BILLING_READY, self::BILLING_SENT, self::BILLING_SUBMITTED], true)
            && empty(array_intersect($this->issue_flags ?? [], ['missing_authorization', 'expired_authorization', 'missing_visit_verification', 'over_authorized_hours']));
    }

    public function usesSigmaPortal(): bool
    {
        if ($this->program_type !== self::PROGRAM_DHS) {
            return false;
        }

        $channel = strtolower((string) $this->submission_channel);
        $route = strtolower((string) $this->billing_route);

        return str_contains($channel, 'sigma')
            || str_contains($channel, 'home help')
            || str_contains($route, 'sigma');
    }

    public function submissionChannelKey(): string
    {
        if ($this->usesAvaility()) {
            return 'availity';
        }

        if ($this->usesSigmaPortal()) {
            return 'sigma';
        }

        if ($this->isDhs()) {
            return 'mdhhs';
        }

        return 'manual';
    }

    public function submissionChannelUrl(): ?string
    {
        return match ($this->submissionChannelKey()) {
            'availity' => 'https://www.availity.com',
            'sigma', 'mdhhs' => app(\App\Services\Billing\SigmaPortalBillingService::class)->portalUrl(),
            default => null,
        };
    }

    public function submissionChannelLabel(): string
    {
        return (string) ($this->submission_channel ?: 'Manual');
    }

    /** The manual submission door for this claim (Availity/clearinghouse dropped). */
    public function channelDoorLabel(): string
    {
        if ($this->isDhs()) {
            return 'DHS Home Help → ASW (email invoice)';
        }
        if ($this->program_type === 'DAAA') {
            return 'Compass portal (DAAA)';
        }

        return 'Office Ally portal';
    }

    /**
     * Channel column display: once manually submitted, show what the operator
     * recorded; otherwise show the manual door this claim goes out through.
     */
    public function displayChannel(): string
    {
        if ($this->submitted_by && filled($this->submission_channel)) {
            return (string) $this->submission_channel;
        }

        return $this->channelDoorLabel();
    }

    public function usesAvaility(): bool
    {
        if ($this->program_type !== self::PROGRAM_MICH) {
            return false;
        }

        $channel = strtolower((string) $this->submission_channel);
        $route = strtolower((string) $this->billing_route);

        return str_contains($channel, 'availity') || str_contains($route, 'availity');
    }

    public function availityStatusLabel(): ?string
    {
        if (! $this->availity_status) {
            return null;
        }

        return ucwords(str_replace('_', ' ', $this->availity_status));
    }

    public function resolvedPdfPath(): ?string
    {
        if ($this->pdf_path) {
            $relative = str_replace('\\', '/', $this->pdf_path);
            if (is_file(storage_path('app/'.$relative))) {
                return $relative;
            }
        }

        foreach ($this->documents ?? [] as $document) {
            if (empty($document['path'])) {
                continue;
            }

            $relative = str_replace('\\', '/', $document['path']);
            if (is_file(storage_path('app/'.$relative))) {
                return $relative;
            }
        }

        return null;
    }

    public function hasDownloadablePdf(): bool
    {
        return $this->resolvedPdfPath() !== null;
    }
}

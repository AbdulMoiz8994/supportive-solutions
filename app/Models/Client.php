<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToOrganization;
use App\Traits\LogsActivity;

class Client extends Model
{
    use HasFactory, BelongsToOrganization, LogsActivity;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\LocationScope);
    }

    protected $fillable = [
        'first_name',
        'last_name',
        'dob',
        'email',
        'phone',
        'address',
        'home_latitude',
        'home_longitude',
        'county',
        'member_id',
        'coverage_type_id',
        'status_id',
        'status',
        'office_location',
        'location_id',
        'billing_rate',
        'organization_id',
        'ssn_last4',
        'ssn_encrypted',
        // Personal / demographics
        'gender',
        'preferred_language',
        'requires_translator',
        // Eligibility & Insurance
        'mco_name',
        'medicare_id',
        'health_plan_id',
        // PCP & medical
        'medical_conditions',
        // Household / Live-In
        'lives_with_caregiver',
        'evv_status',
        'live_in_exemption_status',
        'live_in_exemption_submitted_at',
        'live_in_exemption_approved_at',
        'live_in_exemption_expires_at',
        'onboarding_steps',
        // Intake & Screening tab
        'referral_source',
        'referral_received_date',
        'referred_by',
        'currently_receiving_care',
        'intake_taken_by',
        'intake_date',
        'eligibility_verified_date',
        'eligibility_result',
        'services_requested',
        'initial_notes',
    ];

    protected $casts = [
        'dob' => 'date',
        'lives_with_caregiver' => 'boolean',
        'live_in_exemption_submitted_at' => 'date',
        'live_in_exemption_approved_at' => 'date',
        'live_in_exemption_expires_at' => 'date',
        'onboarding_steps' => 'array',
        'ssn_encrypted' => 'encrypted',
        'home_latitude' => 'float',
        'home_longitude' => 'float',
        // Intake & Screening
        'referral_received_date' => 'date',
        'intake_date' => 'date',
        'eligibility_verified_date' => 'date',
        'services_requested' => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function statusRecord()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function coverageType()
    {
        return $this->belongsTo(CoverageType::class);
    }

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'client_contact')->withPivot('role');
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'client_employee');
    }

    public function caregiverAssignments()
    {
        return $this->hasMany(CaregiverAssignment::class);
    }

    public function careDetails()
    {
        return $this->hasMany(CareDetail::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class)->withoutGlobalScopes();
    }

    public function billings()
    {
        return $this->hasMany(Billing::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function requests()
    {
        return $this->hasMany(ClientRequest::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(ClientStatusHistory::class)->orderByDesc('effective_date');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Display helpers for the Client module UI (registry + profile).
    // All are null-safe so views render whether or not relations are loaded.
    // ──────────────────────────────────────────────────────────────────────

    /** A client stuck this many days in a waiting status needs office attention. */
    public const STATUS_ATTENTION_WINDOW_DAYS = 45;

    /** Statuses where sitting too long is a problem worth flagging. */
    public const WAITING_STATUSES = ['Pending', 'Pending Application', 'On Hold', 'Request Medical Needs Form', 'Awaiting Medical Needs Form', 'Pending Approval', 'Pending Transfer'];

    /** Current status name (status record wins over the legacy string column). */
    public function getCurrentStatusNameAttribute(): string
    {
        return $this->statusRecord?->name ?? $this->status ?? 'Active';
    }

    /** When the client entered the current status (history wins, else created_at). */
    public function getCurrentStatusSinceAttribute(): ?\Carbon\Carbon
    {
        $name = $this->current_status_name;

        $since = $this->relationLoaded('statusHistories')
            ? $this->statusHistories->firstWhere('to_status', $name)?->effective_date
            : $this->statusHistories()->where('to_status', $name)->first()?->effective_date;

        $since = $since ?? $this->created_at;

        return $since ? \Carbon\Carbon::parse($since) : null;
    }

    /** Whole days the client has sat in the current status. */
    public function getDaysInCurrentStatusAttribute(): ?int
    {
        $since = $this->current_status_since;

        return $since ? (int) $since->copy()->startOfDay()->diffInDays(now()->startOfDay()) : null;
    }

    /** True when a waiting status has exceeded the attention window — drives the stale-status alert. */
    public function getStatusNeedsAttentionAttribute(): bool
    {
        $days = $this->days_in_current_status;

        return $days !== null
            && $days > self::STATUS_ATTENTION_WINDOW_DAYS
            && in_array($this->current_status_name, self::WAITING_STATUSES, true);
    }

    /** Query helper: clients currently in a waiting status (candidates for the stale-status sweep). */
    public function scopeInWaitingStatus($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('status', self::WAITING_STATUSES)
              ->orWhereHas('statusRecord', fn ($s) => $s->whereIn('name', self::WAITING_STATUSES));
        });
    }

    /** Age in whole years from dob, or null. */
    public function getAgeAttribute(): ?int
    {
        return $this->dob ? \Carbon\Carbon::parse($this->dob)->age : null;
    }

    /**
     * Coarse program bucket used for billing routing, filters and KPIs: DHS vs
     * everything-else (MICH). Kept intentionally binary — many call sites branch on
     * `=== 'DHS'` / `=== 'MICH'`. For the true program shown in list columns use
     * {@see getProgramDisplayAttribute()}.
     */
    public function getProgramLabelAttribute(): string
    {
        $name = (string) ($this->coverageType?->name ?? '');
        if ($name === '') {
            return '—';
        }
        if (stripos($name, 'DHS') !== false || stripos($name, 'Home Help') !== false) {
            return 'DHS';
        }

        return 'MICH';
    }

    /**
     * Full program code for display columns: DHS / MICH / ICO / DAAA / Private Pay.
     * DHS collapses "DHS Home Help"; the managed-care programs stay distinct so a
     * row never mislabels an ICO or DAAA client as "MICH".
     */
    public function getProgramDisplayAttribute(): string
    {
        $name = trim((string) ($this->coverageType?->name ?? ''));

        if ($name === '') {
            return '—';
        }

        if (stripos($name, 'DHS') !== false || stripos($name, 'Home Help') !== false) {
            return 'DHS';
        }

        foreach (['MICH', 'ICO', 'DAAA'] as $code) {
            if (stripos($name, $code) !== false) {
                return $code;
            }
        }

        if (stripos($name, 'Private') !== false || stripos($name, 'Self-Pay') !== false) {
            return 'Private Pay';
        }

        return $name;
    }

    /** Most recent authorization (care detail) by end date. */
    public function currentAuthorization(): ?CareDetail
    {
        if ($this->relationLoaded('careDetails')) {
            return $this->careDetails->sortByDesc('end_date')->first();
        }

        return $this->careDetails()->orderByDesc('end_date')->first();
    }

    /**
     * Authorization status summary: ['label', 'tone', 'days'].
     * tone maps to the <x-ui.pill> variants.
     */
    public function authStatus(): array
    {
        $auth = $this->currentAuthorization();

        if (! $auth || ! $auth->end_date) {
            return ['label' => 'No auth', 'tone' => 'gray', 'days' => null];
        }

        $days = (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($auth->end_date)->startOfDay(), false);

        // DHS Home Help is Time/Task: the end date is a 6-month reassessment, not an
        // expiry. Service never stops, so it must never read "Expired" — surface the
        // reassessment cadence instead (mirrors the Authorizations page rule).
        if ($this->program_label === 'DHS') {
            if ($days <= 60) {
                return ['label' => $days < 0 ? 'Reassess due' : "Reassess {$days}d", 'tone' => 'amber', 'days' => $days];
            }

            return ['label' => 'Active', 'tone' => 'green', 'days' => $days];
        }

        // Unknown program (no coverage type on file): expiry semantics depend on
        // the program (DHS never expires), so don't claim "Expired" — flag the
        // missing data instead of misclassifying the client as on-hold.
        if ($this->program_label === '—' && $days < 0) {
            return ['label' => 'Verify program', 'tone' => 'amber', 'days' => $days];
        }

        if ($days < 0) {
            return ['label' => 'Expired', 'tone' => 'red', 'days' => $days];
        }
        if ($days <= 21) {
            return ['label' => "Expires {$days}d", 'tone' => 'amber', 'days' => $days];
        }

        return ['label' => 'Active', 'tone' => 'green', 'days' => $days];
    }

    /** The current active caregiver assignment record (not the employee), or null. */
    public function getActiveAssignmentAttribute(): ?CaregiverAssignment
    {
        $assignments = $this->relationLoaded('caregiverAssignments')
            ? $this->caregiverAssignments
            : $this->caregiverAssignments()->get();

        return $assignments
            ->where('status', 'Active')
            ->sortByDesc(fn (CaregiverAssignment $a) => optional($a->assigned_since)->timestamp ?? optional($a->created_at)->timestamp ?? 0)
            ->first();
    }

    /** Current active caregiver (employee), or null. */
    public function getPrimaryCaregiverAttribute(): ?Employee
    {
        $assignment = $this->relationLoaded('caregiverAssignments')
            ? $this->caregiverAssignments
                ->where('status', 'Active')
                ->sortByDesc(function (CaregiverAssignment $assignment) {
                    return optional($assignment->assigned_since)->timestamp ?? optional($assignment->created_at)->timestamp ?? 0;
                })
                ->first()
            : $this->caregiverAssignments()
                ->where('status', 'Active')
                ->orderByDesc('assigned_since')
                ->orderByDesc('id')
                ->first();

        if ($assignment?->employee_id) {
            return Employee::withoutGlobalScopes()->find($assignment->employee_id);
        }

        return $this->relationLoaded('employees')
            ? $this->employees->first()
            : $this->employees()->first();
    }

    /** Resolve the case coordinator from linked contacts (pivot role or type). */
    public function caseCoordinator(): ?Contact
    {
        $contacts = $this->relationLoaded('contacts') ? $this->contacts : $this->contacts()->get();

        $coordinator = $contacts->first(function (Contact $c) {
            $role = strtolower($c->pivot->role ?? '');

            return str_contains($role, 'coordinator') || $c->type === 'Case Coordinator';
        });

        // Legacy fallback for role-less links — but never mislabel an
        // emergency contact, PCP or ASW as the coordinator.
        return $coordinator ?? $contacts->first(function (Contact $c) {
            $role = strtolower($c->pivot->role ?? '');

            return $role === ''
                && ! in_array($c->type, [Contact::TYPE_FAMILY_EMERGENCY, Contact::TYPE_PCP, Contact::TYPE_AGENCY_STAFF], true);
        });
    }

    /** Linked DHS Adult Services Worker (Directory → DHS ASWs). */
    public function aswContact(): ?Contact
    {
        $contacts = $this->relationLoaded('contacts') ? $this->contacts : $this->contacts()->get();

        return $contacts->first(function (Contact $c) {
            $role = strtolower($c->pivot->role ?? '');

            return str_contains($role, 'asw')
                || str_contains($role, 'adult services')
                || str_contains(strtolower($c->type ?? ''), 'asw');
        });
    }

    /** Emergency contact from linked contacts (pivot role contains "emergency"). */
    public function emergencyContact(): ?Contact
    {
        $contacts = $this->relationLoaded('contacts') ? $this->contacts : $this->contacts()->get();

        return $contacts->first(function (Contact $c) {
            return str_contains(strtolower($c->pivot->role ?? ''), 'emergency');
        });
    }

    /** Pill tone for the client's current status. */
    public function getStatusToneAttribute(): string
    {
        return match (strtolower((string) ($this->statusRecord?->name ?? $this->status))) {
            'active'                  => 'green',
            'recovery'                => 'blue',
            'on hold', 'hold'         => 'amber',
            'pending'                 => 'blue',
            'denied'                  => 'red',
            'discharged', 'deceased', 'inactive' => 'gray',
            default                   => 'blue',
        };
    }
}

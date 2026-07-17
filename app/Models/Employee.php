<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToOrganization;

class Employee extends Model
{
    use HasFactory, BelongsToOrganization;

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
        'user_id',
        'email',
        'phone',
        'address',
        'position',
        'champs_username',
        'champs_password',
        'champs_association_date',
        'status_id',
        'status',
        'office_location',
        'location_id',
        'hire_date',
        'organization_id',
        'date_of_birth',
        'preferred_language',
        'id_expiry_date',
        'city',
        'state',
        'zip_code',
        'is_18_plus',
        'is_work_eligible',
        'has_background_check',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'scan_id_path',
        // caregiver module
        'gender', 'ssn_last4', 'county', 'emergency_contact_email', 'profile_photo',
        'needs_accommodations', 'caregiver_type', 'relationship_to_client', 'how_recruited',
        'pay_type', 'pay_schedule', 'w4_filing_status', 'direct_deposit_last4',
        'insurance_coverage', 'classification', 'payroll_system', 'onboarding_status',
        'onboarded_by', 'champs_provider_id', 'champs_status', 'milogin_user_id',
        'attestation_status', 'years_experience', 'prior_experience', 'services',
        'hourly_wage', 'lives_with_client', 'live_in', 'evv_exempt', 'notes',
        'activated_at', 'pay_eligibility_start', 'attestation_expires_at', 'application_signed_at',
        'metadata',
        'aw_employee_id', 'aw_setup_status', 'aw_setup_error', 'aw_setup_http_status', 'aw_setup_error_context', 'aw_setup_payload', 'aw_setup_attempted_at',
    ];

    protected $hidden = [
        // Deprecated: use Credential Vault (Global Settings) for CHAMPS credentials.
        'champs_password',
    ];

    protected $casts = [
        'services'                => 'array',
        'is_18_plus'              => 'boolean',
        'is_work_eligible'        => 'boolean',
        'has_background_check'    => 'boolean',
        'needs_accommodations'    => 'boolean',
        'prior_experience'        => 'boolean',
        'lives_with_client'       => 'boolean',
        'live_in'                 => 'boolean',
        'evv_exempt'              => 'boolean',
        'hourly_wage'             => 'decimal:2',
        'date_of_birth'           => 'date',
        'hire_date'               => 'date',
        'champs_association_date' => 'date',
        'id_expiry_date'          => 'date',
        'activated_at'            => 'date',
        'pay_eligibility_start'   => 'date',
        'attestation_expires_at'  => 'date',
        'application_signed_at'   => 'date',
        'metadata'                => 'array',
        'aw_setup_payload'        => 'encrypted:array',
        'aw_setup_attempted_at'   => 'datetime',
    ];

    public const AW_SETUP_SYNCED = 'synced';

    public const AW_SETUP_FAILED = 'failed';

    public function getNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // ── Credential / compliance expiry flagging (backend automation, no LLM) ──

    /** A credential within this many days of expiry is flagged "Expiring Soon". */
    public const CREDENTIAL_EXPIRY_WINDOW_DAYS = 30;

    private function dateExpiryStatus(?\Illuminate\Support\Carbon $date): string
    {
        if (! $date) {
            return 'Unknown';
        }
        $days = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);
        if ($days < 0) {
            return 'Expired';
        }
        if ($days <= self::CREDENTIAL_EXPIRY_WINDOW_DAYS) {
            return 'Expiring Soon';
        }
        return 'Valid';
    }

    public function getIdExpiryStatusAttribute(): string
    {
        return $this->dateExpiryStatus($this->id_expiry_date);
    }

    public function getAttestationExpiryStatusAttribute(): string
    {
        return $this->dateExpiryStatus($this->attestation_expires_at);
    }

    /** CHAMPS association is what lets a caregiver legally work / collect hours. */
    public function getIsChampsAssociatedAttribute(): bool
    {
        return $this->champs_association_date !== null;
    }

    /**
     * Human-readable compliance alerts for the caregiver, each ['label','tone'].
     * tone: 'red' = blocking/expired, 'amber' = attention soon.
     */
    public function getCredentialAlertsAttribute(): array
    {
        $alerts = [];

        if ($this->id_expiry_status === 'Expired') {
            $alerts[] = ['label' => 'ID expired', 'tone' => 'red'];
        } elseif ($this->id_expiry_status === 'Expiring Soon') {
            $alerts[] = ['label' => 'ID expiring soon', 'tone' => 'amber'];
        }

        if (! $this->is_champs_associated) {
            $alerts[] = ['label' => 'Not CHAMPS-associated — cannot collect hours', 'tone' => 'red'];
        }

        if ($this->has_background_check === false) {
            $alerts[] = ['label' => 'Background check incomplete', 'tone' => 'red'];
        }

        if ($this->attestation_expires_at) {
            if ($this->attestation_expiry_status === 'Expired') {
                $alerts[] = ['label' => 'Live-in attestation expired', 'tone' => 'red'];
            } elseif ($this->attestation_expiry_status === 'Expiring Soon') {
                $alerts[] = ['label' => 'Live-in attestation expiring soon', 'tone' => 'amber'];
            }
        }

        return $alerts;
    }

    // ── Hours rollup across assigned clients (backend automation, no LLM) ──────

    /** Total authorized weekly hours across every client this caregiver serves. */
    public function getTotalWeeklyHoursAttribute(): float
    {
        return round($this->clients->sum(function (Client $client) {
            return (float) ($client->currentAuthorization()?->hours_per_week_value ?? 0);
        }), 2);
    }

    /** Total authorized weekly hours spread across 7 days. */
    public function getTotalDailyHoursAttribute(): float
    {
        return round($this->total_weekly_hours / 7, 2);
    }

    public function getAssignedClientCountAttribute(): int
    {
        return $this->clients->count();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function statusRecord()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class, 'client_employee');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class)->withoutGlobalScopes();
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    // ── Caregiver module relationships ──────────────────────────────

    public function backgroundChecks()
    {
        return $this->hasMany(BackgroundCheck::class);
    }

    public function assignments()
    {
        return $this->hasMany(CaregiverAssignment::class);
    }

    public function activeAssignment()
    {
        return $this->hasOne(CaregiverAssignment::class)->where('status', 'Active')->latestOfMany();
    }

    public function complianceForms()
    {
        return $this->hasMany(ComplianceForm::class);
    }

    public function payRecords()
    {
        return $this->hasMany(PayRecord::class);
    }

    public function communications()
    {
        return $this->hasMany(CaregiverCommunication::class);
    }

    public function caregiverNotes()
    {
        return $this->hasMany(CaregiverNote::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(CaregiverAuditLog::class);
    }

    public function isAccountantsWorldSynced(): bool
    {
        return $this->aw_setup_status === self::AW_SETUP_SYNCED;
    }

    public function isAwaitingAccountantsWorldSetup(): bool
    {
        return $this->aw_setup_status === self::AW_SETUP_FAILED;
    }

    public function scopeAwaitingAccountantsWorldSetup($query)
    {
        return $query->where('aw_setup_status', self::AW_SETUP_FAILED);
    }

    public function getAwSetupErrorDisplayAttribute(): string
    {
        return app(\App\Services\Payroll\AccountantsWorldErrorFormatter::class)->formatStored($this);
    }

    public function getAwSetupContextLabelAttribute(): string
    {
        return match ($this->aw_setup_error_context) {
            \App\Services\Payroll\AccountantsWorldErrorFormatter::CONTEXT_VERIFY => 'Verify failed',
            \App\Services\Payroll\AccountantsWorldErrorFormatter::CONTEXT_LEGACY => 'Needs recheck',
            default => 'Create failed',
        };
    }
}

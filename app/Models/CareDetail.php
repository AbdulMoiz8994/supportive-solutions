<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToOrganization;

class CareDetail extends Model
{
    use HasFactory, BelongsToOrganization;

    /** Renewal should be started this many days before the authorization expires (EMR protocol). */
    public const RENEWAL_WINDOW_DAYS = 45;

    protected $fillable = [
        'client_id',
        'billing_code',
        'start_date',
        'end_date',
        'total_units',
        'units_used',
        'hours_per_week',
        'status',
        'authorized_by',
        'organization_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'hours_per_week' => 'float',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Weekly authorized hours. Prefers the stored value, else derives it from
     * 15-minute units: T1019 is billed in 15-min units, so units / 4 = hours/week.
     */
    public function getHoursPerWeekValueAttribute(): ?float
    {
        if ($this->hours_per_week !== null) {
            return round((float) $this->hours_per_week, 2);
        }

        return $this->total_units ? round($this->total_units / 4, 2) : null;
    }

    /** Average hours per day — weekly hours spread across 7 days. */
    public function getHoursPerDayAttribute(): ?float
    {
        $weekly = $this->hours_per_week_value;

        return $weekly !== null ? round($weekly / 7, 2) : null;
    }

    /** Average hours per month — weekly hours over the average 4.345-week month. */
    public function getHoursPerMonthAttribute(): ?float
    {
        $weekly = $this->hours_per_week_value;

        return $weekly !== null ? round($weekly * (365 / 12 / 7), 1) : null;
    }

    /** Signed days until expiry: positive = future, negative = already expired, null = no end date. */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false);
    }

    public function getIsExpiredAttribute(): bool
    {
        $days = $this->days_until_expiry;

        return $days !== null && $days < 0;
    }

    /** True when the authorization is within the renewal window and not yet expired. */
    public function getNeedsRenewalAttribute(): bool
    {
        $days = $this->days_until_expiry;

        return $days !== null && $days >= 0 && $days <= self::RENEWAL_WINDOW_DAYS;
    }

    /**
     * Live coverage status derived from dates (does not depend on a cron flip).
     * Program-aware when the client relation is loaded; otherwise treats as MICH PA rules.
     */
    public function getEffectiveStatusAttribute(): string
    {
        return $this->effectiveStatusForProgram($this->client?->program_label ?? 'MICH');
    }

    /** Agency registry auth ref — TT-* for DHS Time/Task, PA-* for MICH prior auths. */
    public function authRefForProgram(string $program): string
    {
        $year = $this->start_date?->format('Y') ?? now()->format('Y');

        if ($program === 'DHS') {
            return 'TT-'.$year.'-'.str_pad((string) $this->id, 2, '0', STR_PAD_LEFT);
        }

        return 'PA-'.$year.'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Display status honoring program rules (mirrors the Authorizations registry).
     * DHS Time/Task never reads "Expired"; overdue DHS reads "Reassessment due".
     */
    public function effectiveStatusForProgram(string $program): string
    {
        if ($program === 'DHS') {
            $reassessDate = $this->end_date ?? ($this->start_date?->copy()->addMonths(6));
            if (! $reassessDate) {
                return 'Active';
            }

            $daysToReassess = (int) now()->startOfDay()->diffInDays($reassessDate->copy()->startOfDay(), false);

            if ($daysToReassess <= 0) {
                return 'Reassessment due';
            }
            if ($daysToReassess <= 60) {
                return 'Reassess soon';
            }

            return 'Active';
        }

        if ($program === '—') {
            if ($this->is_expired) {
                return 'Verify program';
            }
            if ($this->needs_renewal) {
                return 'Expiring Soon';
            }

            return 'Active';
        }

        if ($this->is_expired) {
            return 'Expired';
        }

        if ($this->needs_renewal) {
            return 'Expiring Soon';
        }

        return 'Active';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToOrganization;
use App\Traits\LogsActivity;

class Intake extends Model
{
    use HasFactory, BelongsToOrganization, LogsActivity;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\LocationScope);
    }

    public const ELIGIBILITY_ELIGIBLE = 'eligible';

    public const ELIGIBILITY_NEEDS_VERIFICATION = 'needs_verification';

    public const ELIGIBILITY_INELIGIBLE = 'ineligible';

    protected $fillable = [
        'first_name',
        'last_name',
        'dob',
        'phone',
        'email',
        'member_id',
        'address',
        'mco_name',
        'source',
        'status',
        'status_id',
        'notes',
        'converted_client_id',
        'location_id',
        'organization_id',
        'id_expiry',
        'champs_association_date',
        'scan_id',
        'scan_data',
        'eligibility_status',
        'eligibility_note',
        'eligibility_checked_at',
        'recommended_program',
        'program_track',
        'hours_per_week',
        'pa_units',
        'assigned_employee_id',
        'coverage_type_id',
    ];

    protected $casts = [
        'scan_data' => 'array',
        'scanned_documents' => 'array',
        'eligibility_checked_at' => 'datetime',
        'dob' => 'date',
        'hours_per_week' => 'float',
    ];

    public function statusRecord()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function convertedClient()
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function coverageType()
    {
        return $this->belongsTo(CoverageType::class);
    }

    public function assignedEmployee()
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function isDhsTrack(): bool
    {
        return ($this->program_track ?? '') === 'dhs';
    }

    public function isManagedCareTrack(): bool
    {
        return in_array($this->program_track, ['mich', 'ico', 'daaa'], true);
    }

    public function displayStatus(): string
    {
        if ($this->converted_client_id) {
            return 'Converted';
        }

        return $this->statusRecord?->name ?? $this->status ?? 'New';
    }

    public function scopeConverted($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('converted_client_id')
                ->orWhere('status', 'Converted')
                ->orWhereHas('statusRecord', fn ($s) => $s->where('name', 'Converted'));
        });
    }
}

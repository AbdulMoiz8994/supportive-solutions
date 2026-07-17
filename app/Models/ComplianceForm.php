<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceForm extends Model
{
    protected $guarded = [];

    protected $casts = [
        'days'                       => 'array',
        'certification'              => 'array',
        'service_start'              => 'date',
        'service_end'                => 'date',
        'submitted_at'               => 'datetime',
        'acknowledgments_initialed'  => 'boolean',
    ];

    public const STATUS_SUBMITTED = 'Submitted';

    public const STATUS_DUE = 'Due';

    public const STATUS_AWAITING = 'Awaiting';

    /** Submitted form confirmed by the monthly wellness call (client review D4). */
    public const STATUS_VERIFIED = 'Verified';

    /**
     * The monthly certification questions the caregiver answers on the mobile
     * app. `{month}` is filled with the certified month's label at read time.
     *
     * @return array<int, array{key: string, text: string}>
     */
    public static function certificationQuestions(): array
    {
        return [
            ['key' => 'provided_services',   'text' => 'Did you provide services during {month}?'],
            ['key' => 'client_hospitalized', 'text' => 'Was the client hospitalized during this month?'],
            ['key' => 'missed_visits',       'text' => 'Were there any missed or skipped visits?'],
            ['key' => 'condition_changed',   'text' => "Did the client's condition change significantly?"],
            ['key' => 'services_as_planned', 'text' => 'Were all scheduled services provided as planned?'],
            ['key' => 'certify_accurate',    'text' => 'Do you certify the information above is accurate?'],
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A record of a click-to-call ("Call Now") attempt from the caregiver app.
 *
 * mode:   'ringout' — RingCentral bridged the call (agency caller ID)
 *         'manual'  — the device dialled the client directly (tel:)
 * status: 'initiated' — RingOut accepted; the caregiver's phone will ring
 *         'manual'    — no bridge placed; the app dials natively
 */
class CallLog extends Model
{
    public const MODE_RINGOUT = 'ringout';

    public const MODE_MANUAL = 'manual';

    public const STATUS_INITIATED = 'initiated';

    public const STATUS_MANUAL = 'manual';

    protected $fillable = [
        'organization_id',
        'user_id',
        'employee_id',
        'client_id',
        'client_name',
        'direction',
        'mode',
        'status',
        'provider',
        'provider_call_id',
        'to_number',
        'from_number',
        'failure_reason',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

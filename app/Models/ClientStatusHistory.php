<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientStatusHistory extends Model
{
    protected $fillable = [
        'client_id',
        'from_status',
        'to_status',
        'effective_date',
        'last_service_date',
        'reason',
        'note',
        'changed_by',
        'changed_by_name',
    ];

    protected $casts = [
        'effective_date'    => 'date',
        'last_service_date' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToOrganization;

class Billing extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'client_id',
        'invoice_number',
        'period_start',
        'period_end',
        'total_amount',
        'status',
        'eob_path',
        'organization_id',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

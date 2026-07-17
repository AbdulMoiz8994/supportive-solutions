<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientRequest extends Model
{
    use HasFactory, BelongsToOrganization;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_MANUAL = 'manual';

    protected $fillable = [
        'organization_id',
        'client_id',
        'request_template_id',
        'sent_by',
        'coordinator_id',
        'template',
        'method',
        'delivery_method',
        'recipient_type',
        'recipient_email',
        'recipient_fax',
        'subject',
        'body_snapshot',
        'notes',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function coordinator()
    {
        return $this->belongsTo(Contact::class, 'coordinator_id');
    }

    public function requestTemplate()
    {
        return $this->belongsTo(RequestTemplate::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}

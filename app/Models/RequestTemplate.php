<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestTemplate extends Model
{
    use BelongsToOrganization, SoftDeletes;

    public const DELIVERY_EMAIL = 'email';

    public const DELIVERY_FAX = 'fax';

    public const DELIVERY_BOTH = 'both';

    public const DELIVERY_MANUAL = 'manual';

    public const RECIPIENT_CASE_COORDINATOR = 'case_coordinator';

    public const RECIPIENT_PCP = 'primary_care_physician';

    public const RECIPIENT_CUSTOM = 'custom';

    public const RECIPIENT_OTHER = 'other';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'category',
        'delivery_method',
        'recipient_type',
        'default_recipient_email',
        'default_recipient_fax',
        'subject',
        'body',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function clientRequests()
    {
        return $this->hasMany(ClientRequest::class);
    }

    public static function deliveryMethods(): array
    {
        return [
            self::DELIVERY_EMAIL,
            self::DELIVERY_FAX,
            self::DELIVERY_BOTH,
            self::DELIVERY_MANUAL,
        ];
    }

    public static function recipientTypes(): array
    {
        return [
            self::RECIPIENT_CASE_COORDINATOR,
            self::RECIPIENT_PCP,
            self::RECIPIENT_CUSTOM,
            self::RECIPIENT_OTHER,
        ];
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CommunicationTemplate extends Model
{
    use BelongsToOrganization, LogsActivity, SoftDeletes;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_FAX = 'fax';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_INTERNAL = 'internal';

    public const STRATEGY_MANUAL = 'manual';

    public const STRATEGY_CLIENT_PCP = 'client_pcp';

    public const STRATEGY_CLIENT_CASE_COORDINATOR = 'client_case_coordinator';

    public const STRATEGY_EMPLOYEE = 'employee';

    public const STRATEGY_CUSTOM_CONTACT = 'custom_contact';

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'channel',
        'subject',
        'body',
        'description',
        'recipient_strategy',
        'default_recipient',
        'allowed_variables',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'allowed_variables' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $template) {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class, 'template_id');
    }

    public static function channels(): array
    {
        return [
            self::CHANNEL_EMAIL,
            self::CHANNEL_FAX,
            self::CHANNEL_SMS,
            self::CHANNEL_INTERNAL,
        ];
    }

    public static function recipientStrategies(): array
    {
        return [
            self::STRATEGY_MANUAL,
            self::STRATEGY_CLIENT_PCP,
            self::STRATEGY_CLIENT_CASE_COORDINATOR,
            self::STRATEGY_EMPLOYEE,
            self::STRATEGY_CUSTOM_CONTACT,
        ];
    }
}

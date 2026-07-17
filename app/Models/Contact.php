<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory, BelongsToOrganization, LogsActivity;

    public const TYPE_PCP = 'Primary Care Physician';

    public const TYPE_CASE_COORDINATOR = 'Case Coordinator';

    public const TYPE_INSURANCE = 'Insurance Contact';

    public const TYPE_REFERRAL = 'Referral Source';

    public const TYPE_FAMILY_EMERGENCY = 'Family / Emergency Contact';

    public const TYPE_AGENCY_STAFF = 'Agency Staff Contact';

    public const TYPE_VENDOR = 'Vendor';

    public const TYPE_PHARMACY = 'Pharmacy';

    public const TYPE_OTHER = 'Other';

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\LocationScope);
    }

    public const CLAIM_CHANNEL_AVAILITY = 'availity';

    public const CLAIM_CHANNEL_SEPARATE_EDI = 'separate_edi';

    protected $fillable = [
        'name',
        'type',
        'job_title',
        'phone',
        'fax',
        'email',
        'clinic_name',
        'provider_id',
        'claim_channel',
        'contracted_rate',
        'parent_contact_id',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'county',
        'zip',
        'notes',
        'is_active',
        'integration_slug',
        'integration_credential_key',
        'data_flow',
        'app_area',
        'owning_agent',
        'location_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'contracted_rate' => 'decimal:2',
    ];

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_PCP,
            self::TYPE_CASE_COORDINATOR,
            self::TYPE_INSURANCE,
            self::TYPE_REFERRAL,
            self::TYPE_FAMILY_EMERGENCY,
            self::TYPE_AGENCY_STAFF,
            self::TYPE_VENDOR,
            self::TYPE_PHARMACY,
            self::TYPE_OTHER,
        ];
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class, 'client_contact')->withPivot('role');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function connectionHealth()
    {
        return $this->hasOne(IntegrationConnectionHealth::class);
    }

    public function parentContact()
    {
        return $this->belongsTo(self::class, 'parent_contact_id');
    }

    public function childContacts()
    {
        return $this->hasMany(self::class, 'parent_contact_id');
    }

    /**
     * @return list<string>
     */
    public static function claimChannels(): array
    {
        return [
            self::CLAIM_CHANNEL_AVAILITY,
            self::CLAIM_CHANNEL_SEPARATE_EDI,
        ];
    }

    public function isIntegrationCard(): bool
    {
        return $this->type === self::TYPE_VENDOR
            && filled($this->integration_slug ?: $this->integration_credential_key);
    }

    public function resolvedCredentialKey(): ?string
    {
        if (filled($this->integration_credential_key)) {
            return $this->integration_credential_key;
        }

        if (filled($this->integration_slug)) {
            return \App\Support\DirectoryIntegrationCatalog::vendor($this->integration_slug)['credential_key'] ?? null;
        }

        return null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDirectorySearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '' || strlen($search) > 100) {
            return $query;
        }

        $phoneDigits = preg_replace('/\D/', '', $search) ?? '';

        return $query->where(function (Builder $builder) use ($search, $phoneDigits) {
            $builder->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('clinic_name', 'like', '%'.$search.'%')
                ->orWhere('job_title', 'like', '%'.$search.'%')
                ->orWhere('type', 'like', '%'.$search.'%')
                ->orWhere('notes', 'like', '%'.$search.'%');

            if (strlen($phoneDigits) >= 3) {
                $phoneNormalize = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(%s, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', '')";

                $builder->orWhereRaw(sprintf($phoneNormalize, 'phone').' LIKE ?', ['%'.$phoneDigits.'%'])
                    ->orWhereRaw(sprintf($phoneNormalize, 'fax').' LIKE ?', ['%'.$phoneDigits.'%']);
            }
        });
    }

    public function scopeFilterType(Builder $query, ?string $type): Builder
    {
        if ($type && in_array($type, self::types(), true)) {
            $query->where('type', $type);
        }

        return $query;
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query;
    }

    public function scopeFilterOrganization(Builder $query, ?string $organization): Builder
    {
        $organization = trim((string) $organization);

        if ($organization !== '' && strlen($organization) <= 255) {
            $query->where('clinic_name', 'like', '%'.$organization.'%');
        }

        return $query;
    }

    public function scopeFilterCounty(Builder $query, ?string $county): Builder
    {
        $county = trim((string) $county);

        if ($county !== '' && strlen($county) <= 100) {
            $query->where('county', 'like', '%'.$county.'%');
        }

        return $query;
    }

    public function scopeFilterCity(Builder $query, ?string $city): Builder
    {
        $city = trim((string) $city);

        if ($city !== '' && strlen($city) <= 100) {
            $query->where('city', 'like', '%'.$city.'%');
        }

        return $query;
    }

    public function scopeFilterClaimChannel(Builder $query, ?string $claimChannel): Builder
    {
        if ($claimChannel && in_array($claimChannel, self::claimChannels(), true)) {
            $query->where('claim_channel', $claimChannel);
        }

        return $query;
    }

    public function scopeDirectorySort(Builder $query, ?string $sort, ?string $direction): Builder
    {
        $allowed = ['name', 'type', 'clinic_name', 'phone', 'email', 'is_active', 'created_at', 'clients_count', 'contracted_rate'];
        $sort = in_array($sort, $allowed, true) ? $sort : 'name';
        $direction = strtolower((string) $direction) === 'desc' ? 'desc' : 'asc';

        if ($sort === 'clients_count') {
            return $query->orderBy('clients_count', $direction);
        }

        return $query->orderBy($sort, $direction);
    }

    public function claimChannelLabel(): ?string
    {
        return match ($this->claim_channel) {
            self::CLAIM_CHANNEL_AVAILITY => '837P · Availity',
            self::CLAIM_CHANNEL_SEPARATE_EDI => '837P · Separate EDI',
            default => null,
        };
    }

    public function claimChannelBadgeClasses(): string
    {
        return match ($this->claim_channel) {
            self::CLAIM_CHANNEL_AVAILITY => 'bg-[#dbeafe] text-[#1e40af]',
            self::CLAIM_CHANNEL_SEPARATE_EDI => 'bg-[#e0e7ff] text-[#3730a3]',
            default => 'bg-[#f1f5f9] text-[#475569]',
        };
    }

    public function formattedContractedRate(): ?string
    {
        if ($this->contracted_rate === null) {
            return null;
        }

        return '$'.number_format((float) $this->contracted_rate, 2).'/hr';
    }

    public function typeBadgeClasses(): string
    {
        return match ($this->type) {
            self::TYPE_PCP => 'bg-blue-50 text-blue-700 border-blue-100 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-500/20',
            self::TYPE_CASE_COORDINATOR => 'bg-purple-50 text-purple-700 border-purple-100 dark:bg-purple-500/10 dark:text-purple-300 dark:border-purple-500/20',
            self::TYPE_INSURANCE => 'bg-cyan-50 text-cyan-700 border-cyan-100 dark:bg-cyan-500/10 dark:text-cyan-300 dark:border-cyan-500/20',
            self::TYPE_REFERRAL => 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20',
            self::TYPE_FAMILY_EMERGENCY => 'bg-rose-50 text-rose-700 border-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/20',
            self::TYPE_AGENCY_STAFF => 'bg-indigo-50 text-indigo-700 border-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:border-indigo-500/20',
            self::TYPE_VENDOR => 'bg-orange-50 text-orange-700 border-orange-100 dark:bg-orange-500/10 dark:text-orange-300 dark:border-orange-500/20',
            self::TYPE_PHARMACY => 'bg-teal-50 text-teal-700 border-teal-100 dark:bg-teal-500/10 dark:text-teal-300 dark:border-teal-500/20',
            default => 'bg-gray-50 text-gray-600 border-gray-100 dark:bg-white/5 dark:text-gray-300 dark:border-white/10',
        };
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return strtoupper(collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    }

    public function phoneTelUri(): ?string
    {
        if (! $this->phone) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $this->phone);

        return $digits !== '' ? 'tel:'.$digits : null;
    }

    public function faxTelUri(): ?string
    {
        if (! $this->fax) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $this->fax);

        return $digits !== '' ? 'tel:'.$digits : null;
    }

    public function mailtoUri(): ?string
    {
        return $this->email ? 'mailto:'.$this->email : null;
    }

    public function mapsUrl(): ?string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->zip,
        ]);

        if ($parts === []) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.urlencode(implode(', ', $parts));
    }

    public function formattedAddress(): ?string
    {
        $street = trim(($this->address_line1 ?? '').' '.($this->address_line2 ?? ''));
        $locality = trim(collect([$this->city, $this->state, $this->zip])->filter()->implode(', '));

        $lines = array_filter([$street, $locality, $this->county]);

        return $lines !== [] ? implode("\n", $lines) : null;
    }
}

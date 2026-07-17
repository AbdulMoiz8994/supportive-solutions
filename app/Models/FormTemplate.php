<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormTemplate extends Model
{
    use BelongsToOrganization;

    public const TARGET_CLIENT = 'client';

    public const TARGET_EMPLOYEE = 'employee';

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'target_type',
        'fields',
        'requires_signature',
        'is_compliance_required',
        'is_active',
    ];

    protected $casts = [
        'fields' => 'array',
        'requires_signature' => 'boolean',
        'is_compliance_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function targetLabel(): string
    {
        return $this->target_type === self::TARGET_EMPLOYEE ? 'Caregiver' : 'Client';
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Role Constants
    const ROLE_SUPER_ADMIN = 'Super Administrator';
    const ROLE_ADMIN = 'Administrator';
    const ROLE_STAFF = 'Operations Staff';
    const ROLE_EMPLOYEE = 'Employee';
    const ROLE_AI_AGENT = 'AI Agent';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'organization_id',
        'is_active',
        'invite_token',
        'invite_expires_at',
        'two_factor_verified_at',
        'two_factor_code',
        'two_factor_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'invite_expires_at' => 'datetime',
            'two_factor_expires_at' => 'datetime',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'location_user');
    }

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role', 'name');
    }

    public function hasPermission($permission)
    {
        $agent = $this->relationLoaded('aiAgent') ? $this->aiAgent : $this->aiAgent()->first();
        if ($agent && is_array($agent->permission_slugs) && $agent->permission_slugs !== []) {
            return in_array($permission, $agent->permission_slugs, true);
        }

        $role = $this->roleModel;
        if (!$role) return false;

        return $role->permissions()->where('slug', $permission)->exists();
    }

    // Role Helpers
    public function isSuperAdmin()
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN || $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isStaff()
    {
        return $this->role === self::ROLE_STAFF || $this->isAdmin();
    }

    public function isEmployee()
    {
        return $this->role === self::ROLE_EMPLOYEE;
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function aiAgent()
    {
        return $this->hasOne(AiAgent::class);
    }

    public function isAiAgent(): bool
    {
        return $this->role === self::ROLE_AI_AGENT;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AiAgent extends Model
{
    protected $fillable = [
        'organization_id',
        'slug',
        'name',
        'short_name',
        'role_description',
        'icon',
        'icon_bg',
        'user_id',
        'is_enabled',
        'is_paused',
        'on_watch',
        'autonomy_mode',
        'guardrails',
        'action_autonomy',
        'permission_slugs',
        'scope_programs',
        'scope_client_ids',
        'scope_location_ids',
        'credential_keys',
        'catalog',
        'is_custom',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_paused' => 'boolean',
        'on_watch' => 'boolean',
        'is_custom' => 'boolean',
        'guardrails' => 'array',
        'action_autonomy' => 'array',
        'permission_slugs' => 'array',
        'scope_programs' => 'array',
        'scope_client_ids' => 'array',
        'scope_location_ids' => 'array',
        'credential_keys' => 'array',
        'catalog' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'ai_agent_client', 'ai_agent_id', 'client_id');
    }

    public function isActive(): bool
    {
        return $this->is_enabled && ! $this->is_paused;
    }

    /**
     * Resolved autonomy mode for a catalog action key (auto|queue|monitor).
     */
    public function actionMode(string $actionKey): string
    {
        $stored = collect($this->action_autonomy ?? [])->firstWhere('key', $actionKey);
        if (is_array($stored) && ! empty($stored['mode'])) {
            return (string) $stored['mode'];
        }

        $definition = collect(config("ai_agent_registry.agent_actions.{$this->slug}", []))
            ->firstWhere('key', $actionKey);

        return (string) ($definition['default'] ?? 'queue');
    }

    /**
     * Whether this agent may initiate an action (not disabled/paused, not monitor-only).
     */
    public function canRunAction(string $actionKey): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $this->actionMode($actionKey) !== 'monitor';
    }

    public function catalogValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->catalog, $key, $default);
    }
}

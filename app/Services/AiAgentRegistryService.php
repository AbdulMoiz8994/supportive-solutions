<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\Client;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiAgentRegistryService
{
    public function __construct(
        protected AiAgentUserService $users,
        protected GlobalSettingsService $globalSettings,
    ) {}

    public function ensureCatalog(?int $organizationId): void
    {
        if (! $organizationId) {
            return;
        }

        foreach (config('staff_ai_agents.agents', []) as $slug => $catalog) {
            if (AiAgent::query()->where('organization_id', $organizationId)->where('slug', $slug)->exists()) {
                continue;
            }

            $this->createFromCatalogTemplate($organizationId, $slug, $catalog);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function exportAgent(AiAgent $agent): array
    {
        return [
            'export_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'slug' => $agent->slug,
            'name' => $agent->name,
            'short_name' => $agent->short_name,
            'role_description' => $agent->role_description,
            'icon' => $agent->icon,
            'icon_bg' => $agent->icon_bg,
            'autonomy_mode' => $agent->autonomy_mode,
            'guardrails' => $agent->guardrails ?? [],
            'action_autonomy' => $agent->action_autonomy ?? [],
            'permission_slugs' => $agent->permission_slugs ?? [],
            'scope_programs' => $agent->scope_programs ?? [],
            'scope_location_ids' => $agent->scope_location_ids ?? [],
            'scope_client_ids' => $agent->scope_client_ids ?? [],
            'credential_keys' => $agent->credential_keys ?? [],
            'catalog' => $agent->catalog ?? [],
            'is_custom' => $agent->is_custom,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exportFleet(?int $organizationId): array
    {
        if ($organizationId) {
            $this->ensureCatalog($organizationId);
        }

        $agents = AiAgent::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->orderBy('name')
            ->get()
            ->map(fn (AiAgent $agent) => $this->exportAgent($agent))
            ->values()
            ->all();

        return [
            'export_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'organization_id' => $organizationId,
            'agents' => $agents,
        ];
    }

    public function agentsForOrganization(?int $organizationId): Collection
    {
        if ($organizationId) {
            $this->ensureCatalog($organizationId);
        }

        return AiAgent::query()
            ->with('user')
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->orderBy('name')
            ->get()
            ->map(fn (AiAgent $agent) => $this->present($agent));
    }

    public function findBySlug(?int $organizationId, string $slug): ?AiAgent
    {
        if ($organizationId) {
            $this->ensureCatalog($organizationId);
        }

        return AiAgent::query()
            ->with(['user', 'scopeClients'])
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('slug', $slug)
            ->first();
    }

    public function present(AiAgent $agent): array
    {
        $catalog = $agent->catalog ?? [];
        $guardrails = array_merge([
            'hold_on_gate_fail' => true,
            'auto_resubmit' => false,
            'approval_threshold' => 0,
        ], $agent->guardrails ?? []);

        if ($agent->slug === 'billing' && empty($agent->guardrails['approval_threshold'] ?? null)) {
            $guardrails['approval_threshold'] = (int) ($this->globalSettings->get('automation.approval_threshold') ?? 5000);
        }

        $autonomyLabel = config(
            "staff_ai_agents.autonomy_labels.{$catalog['autonomy']}",
            config("staff_ai_agents.autonomy_modes.{$agent->autonomy_mode}", 'Autonomous'),
        );

        $statusLabel = ! $agent->is_enabled
            ? 'Disabled'
            : ($agent->is_paused ? 'Paused' : ($agent->on_watch ? 'On watch' : 'Healthy'));

        $statusBadge = ! $agent->is_enabled || $agent->is_paused
            ? 'grey'
            : ($agent->on_watch ? 'amber' : 'green');

        return array_merge($catalog, [
            'id' => $agent->id,
            'slug' => $agent->slug,
            'name' => $agent->name,
            'short_name' => $agent->short_name ?? $agent->name,
            'role' => $agent->role_description,
            'icon' => $agent->icon,
            'icon_bg' => $agent->icon_bg,
            'autonomy_mode' => $agent->autonomy_mode,
            'autonomy_label' => $autonomyLabel,
            'is_enabled' => $agent->is_enabled,
            'paused' => $agent->is_paused,
            'on_watch' => $agent->on_watch,
            'is_custom' => $agent->is_custom,
            'guardrails' => $guardrails,
            'action_autonomy' => $this->normalizedActionAutonomy($agent),
            'permission_slugs' => $agent->permission_slugs ?? [],
            'scope_programs' => $agent->scope_programs ?? config('ai_agent_registry.programs', []),
            'scope_client_ids' => $agent->scope_client_ids ?? [],
            'scope_location_ids' => $agent->scope_location_ids ?? [],
            'credential_keys' => $agent->credential_keys ?? [],
            'user_id' => $agent->user_id,
            'user_email' => $agent->user?->email,
            'user_active' => (bool) $agent->user?->is_active,
            'status_label' => $statusLabel,
            'status_badge' => $statusBadge,
            'canApprove' => $agent->isActive(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(?int $organizationId, array $data): AiAgent
    {
        return DB::transaction(function () use ($organizationId, $data) {
            $slug = $this->uniqueSlug($organizationId, $data['slug'] ?? Str::slug($data['name']));

            $agent = AiAgent::create([
                'organization_id' => $organizationId,
                'slug' => $slug,
                'name' => $data['name'],
                'short_name' => $data['short_name'] ?? $data['name'],
                'role_description' => $data['role_description'] ?? null,
                'icon' => $data['icon'] ?? '🤖',
                'icon_bg' => $data['icon_bg'] ?? 'bg-[#dbeafe]',
                'is_enabled' => $data['is_enabled'] ?? true,
                'is_paused' => false,
                'on_watch' => false,
                'autonomy_mode' => $data['autonomy_mode'] ?? 'approval_required',
                'guardrails' => $data['guardrails'] ?? [],
                'action_autonomy' => $data['action_autonomy'] ?? $this->defaultActionAutonomy($slug),
                'permission_slugs' => $data['permission_slugs'] ?? config("ai_agent_registry.default_permissions.{$slug}", ['view_dashboard']),
                'scope_programs' => $data['scope_programs'] ?? config('ai_agent_registry.programs', []),
                'scope_client_ids' => $data['scope_client_ids'] ?? [],
                'scope_location_ids' => $data['scope_location_ids'] ?? [],
                'credential_keys' => $data['credential_keys'] ?? config("ai_agent_registry.default_credentials.{$slug}", []),
                'catalog' => $data['catalog'] ?? [],
                'is_custom' => $data['is_custom'] ?? true,
            ]);

            $this->syncScopeClients($agent, $data['scope_client_ids'] ?? []);
            $this->users->provision($agent);

            return $agent->fresh(['user', 'scopeClients']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AiAgent $agent, array $data): AiAgent
    {
        return DB::transaction(function () use ($agent, $data) {
            $agent->fill([
                'name' => $data['name'] ?? $agent->name,
                'short_name' => $data['short_name'] ?? $agent->short_name,
                'role_description' => $data['role_description'] ?? $agent->role_description,
                'icon' => $data['icon'] ?? $agent->icon,
                'icon_bg' => $data['icon_bg'] ?? $agent->icon_bg,
                'autonomy_mode' => $data['autonomy_mode'] ?? $agent->autonomy_mode,
                'guardrails' => $data['guardrails'] ?? $agent->guardrails,
                'action_autonomy' => $data['action_autonomy'] ?? $agent->action_autonomy,
                'permission_slugs' => $data['permission_slugs'] ?? $agent->permission_slugs,
                'scope_programs' => $data['scope_programs'] ?? $agent->scope_programs,
                'scope_client_ids' => $data['scope_client_ids'] ?? $agent->scope_client_ids,
                'scope_location_ids' => $data['scope_location_ids'] ?? $agent->scope_location_ids,
                'credential_keys' => $data['credential_keys'] ?? $agent->credential_keys,
            ]);

            if (array_key_exists('is_enabled', $data)) {
                $agent->is_enabled = (bool) $data['is_enabled'];
            }

            if (array_key_exists('is_paused', $data)) {
                $agent->is_paused = (bool) $data['is_paused'];
            }

            if (array_key_exists('on_watch', $data)) {
                $agent->on_watch = (bool) $data['on_watch'];
            }

            $agent->save();

            if (array_key_exists('scope_client_ids', $data)) {
                $this->syncScopeClients($agent, $data['scope_client_ids'] ?? []);
            }

            if (array_key_exists('permission_slugs', $data) && $agent->user) {
                $this->users->syncPermissions($agent);
            }

            if ($agent->user) {
                $agent->user->update(['is_active' => $agent->is_enabled && ! $agent->is_paused]);
            }

            return $agent->fresh(['user', 'scopeClients']);
        });
    }

    public function setPaused(AiAgent $agent, bool $paused): AiAgent
    {
        return $this->update($agent, ['is_paused' => $paused]);
    }

    public function setEnabled(AiAgent $agent, bool $enabled): AiAgent
    {
        return $this->update($agent, ['is_enabled' => $enabled, 'is_paused' => $enabled ? $agent->is_paused : true]);
    }

    public function delete(AiAgent $agent): void
    {
        DB::transaction(function () use ($agent) {
            if ($agent->user) {
                $agent->user->tokens()->delete();
                $agent->user->delete();
            }

            $agent->delete();
        });
    }

    /**
     * @return list<AiAgent>
     */
    public function importFromJson(?int $organizationId, UploadedFile $file): array
    {
        $payload = json_decode($file->get(), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new \InvalidArgumentException('Invalid agent JSON.');
        }

        if (isset($payload['agents']) && is_array($payload['agents'])) {
            return collect($payload['agents'])
                ->filter(fn ($row) => is_array($row))
                ->map(fn (array $row) => $this->importPayload($organizationId, $row))
                ->values()
                ->all();
        }

        return [$this->importPayload($organizationId, $payload)];
    }

    /**
     * Map vault credential keys to agent names for the current organization.
     *
     * @return array<string, string>
     */
    public function credentialLabelsByKey(?int $organizationId): array
    {
        if (! $organizationId) {
            return [];
        }

        $labels = [];

        AiAgent::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['name', 'credential_keys'])
            ->each(function (AiAgent $agent) use (&$labels) {
                foreach ($agent->credential_keys ?? [] as $key) {
                    $labels[$key] = isset($labels[$key])
                        ? $labels[$key].' · '.$agent->name
                        : $agent->name;
                }
            });

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function importPayload(?int $organizationId, array $payload): AiAgent
    {
        if (blank($payload['name'] ?? null)) {
            throw new \InvalidArgumentException('Agent JSON must include a name.');
        }

        return $this->create($organizationId, array_merge($payload, [
            'is_custom' => true,
            'slug' => $payload['slug'] ?? null,
        ]));
    }

    public function catalogCount(): int
    {
        return count(config('staff_ai_agents.agents', []));
    }

    public function migrateLegacySettings(?int $organizationId): void
    {
        if (! $organizationId) {
            return;
        }

        Setting::query()
            ->where('group', 'ai_agents')
            ->where('key', 'like', 'ai_agent.%')
            ->get()
            ->each(function (Setting $setting) use ($organizationId) {
                $slug = Str::after($setting->key, 'ai_agent.');
                $agent = AiAgent::query()
                    ->where('organization_id', $organizationId)
                    ->where('slug', $slug)
                    ->first();

                if (! $agent || ! is_array($setting->value_payload)) {
                    return;
                }

                $payload = $setting->value_payload;
                $updates = [];

                if (array_key_exists('paused', $payload)) {
                    $updates['is_paused'] = (bool) $payload['paused'];
                }
                if (isset($payload['autonomy_mode'])) {
                    $updates['autonomy_mode'] = $payload['autonomy_mode'];
                }
                if (isset($payload['guardrails'])) {
                    $updates['guardrails'] = $payload['guardrails'];
                }

                if ($updates !== []) {
                    $agent->update($updates);
                }
            });
    }

    /**
     * @return list<array{key: string, label: string, mode: string}>
     */
    public function actionDefinitions(AiAgent $agent): array
    {
        $definitions = config("ai_agent_registry.agent_actions.{$agent->slug}", []);
        $stored = collect($agent->action_autonomy ?? [])->keyBy('key');
        $modes = config('ai_agent_registry.action_modes', []);

        return collect($definitions)->map(function (array $def) use ($stored) {
            $mode = $stored->get($def['key'])['mode'] ?? $def['default'] ?? 'queue';

            return [
                'key' => $def['key'],
                'label' => $def['label'],
                'mode' => $mode,
                'mode_label' => config("ai_agent_registry.action_modes.{$mode}", $mode),
            ];
        })->values()->all();
    }

    public function formOptions(?int $organizationId): array
    {
        return [
            'programs' => config('ai_agent_registry.programs', []),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'clients' => Client::query()
                ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
                ->orderBy('first_name')
                ->limit(500)
                ->get(['id', 'first_name', 'last_name']),
            'credentials' => collect(\App\Models\IntegrationCredential::supportedKeys())
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'autonomy_modes' => config('staff_ai_agents.autonomy_modes', []),
            'action_modes' => config('ai_agent_registry.action_modes', []),
            'catalog_templates' => collect(config('staff_ai_agents.agents', []))
                ->map(fn ($a, $slug) => ['slug' => $slug, 'name' => $a['name']])
                ->values()
                ->all(),
            'permissions' => \App\Models\Permission::orderBy('module')->orderBy('name')->get(['slug', 'name', 'module']),
        ];
    }

    /**
     * @param  array<string, mixed>  $catalog
     */
    protected function createFromCatalogTemplate(int $organizationId, string $slug, array $catalog): AiAgent
    {
        $agent = AiAgent::create([
            'organization_id' => $organizationId,
            'slug' => $slug,
            'name' => $catalog['name'],
            'short_name' => $catalog['short_name'] ?? $catalog['name'],
            'role_description' => $catalog['role'] ?? null,
            'icon' => $catalog['icon'] ?? '🤖',
            'icon_bg' => $catalog['icon_bg'] ?? 'bg-[#dbeafe]',
            'is_enabled' => true,
            'is_paused' => (bool) ($catalog['paused'] ?? false),
            'on_watch' => (bool) ($catalog['on_watch'] ?? false),
            'autonomy_mode' => $catalog['autonomy_mode'] ?? 'approval_required',
            'guardrails' => $catalog['guardrails'] ?? [],
            'action_autonomy' => $this->defaultActionAutonomy($slug),
            'permission_slugs' => config("ai_agent_registry.default_permissions.{$slug}", ['view_dashboard']),
            'scope_programs' => config('ai_agent_registry.programs', []),
            'scope_client_ids' => [],
            'scope_location_ids' => [],
            'credential_keys' => config("ai_agent_registry.default_credentials.{$slug}", []),
            'catalog' => collect($catalog)->except(['slug'])->all(),
            'is_custom' => false,
        ]);

        $this->users->provision($agent);

        return $agent;
    }

    /**
     * @return list<array{key: string, mode: string}>
     */
    protected function defaultActionAutonomy(string $slug): array
    {
        return collect(config("ai_agent_registry.agent_actions.{$slug}", []))
            ->map(fn (array $def) => ['key' => $def['key'], 'mode' => $def['default'] ?? 'queue'])
            ->values()
            ->all();
    }

    protected function normalizedActionAutonomy(AiAgent $agent): array
    {
        $defaults = collect($this->defaultActionAutonomy($agent->slug))->keyBy('key');
        $stored = collect($agent->action_autonomy ?? [])->keyBy('key');

        return $defaults->merge($stored)->values()->all();
    }

    protected function uniqueSlug(?int $organizationId, string $base): string
    {
        $slug = Str::slug($base) ?: 'agent';
        $candidate = $slug;
        $i = 2;

        while (AiAgent::query()->where('organization_id', $organizationId)->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    /**
     * @param  list<int>  $clientIds
     */
    protected function syncScopeClients(AiAgent $agent, array $clientIds): void
    {
        $agent->scopeClients()->sync($clientIds);
        $agent->update(['scope_client_ids' => $clientIds]);
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class StaffAiAgentsService
{
    public function __construct(
        protected GlobalSettingsService $globalSettings,
        protected AiAgentRegistryService $registry,
    ) {}

    public function ceiling(): float
    {
        return (float) ($this->globalSettings->get('automation.miss_rate_ceiling')
            ?? config('staff_ai_agents.miss_rate_ceiling', 2.0));
    }

    public function defaultApprovalThreshold(): int
    {
        return (int) ($this->globalSettings->get('automation.approval_threshold') ?? 5000);
    }

    public function organizationId(): ?int
    {
        $user = auth()->user();

        if ($user?->organization_id) {
            return (int) $user->organization_id;
        }

        return app(AgencyIdentityService::class)->primaryOrganization()?->id;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function agents(): Collection
    {
        $orgId = $this->organizationId();
        if ($orgId) {
            $this->registry->migrateLegacySettings($orgId);
        }

        return $this->registry->agentsForOrganization($orgId);
    }

    public function agent(string $slug): ?array
    {
        $model = $this->registry->findBySlug($this->organizationId(), $slug);

        return $model ? $this->registry->present($model) : null;
    }

    public function agentModel(string $slug): ?\App\Models\AiAgent
    {
        return $this->registry->findBySlug($this->organizationId(), $slug);
    }

    public function agentsOnWatchCount(): int
    {
        return $this->agents()->where('on_watch', true)->count();
    }

    public function activeAgentsCount(): int
    {
        return $this->agents()->filter(fn (array $a) => ($a['is_enabled'] ?? true) && ! ($a['paused'] ?? false))->count();
    }

    public function fleetKpis(string $tab = 'agents'): array
    {
        $fleet = config('staff_ai_agents.fleet', []);
        $agents = $this->agents();
        $total = $agents->count();
        $active = $this->activeAgentsCount();
        $onWatch = $this->agentsOnWatchCount();
        $ceiling = $this->ceiling();
        $awaitingApproval = app(WorkflowQueueService::class)->approvalCount(
            app(ApprovalQueueMetricsService::class)->approvalOrganizationId()
        );

        if ($tab === 'operations') {
            return [
                ['label' => 'Fleet uptime', 'value' => ($fleet['uptime_pct'] ?? 99.9).'%', 'sub' => '30 days', 'tone' => 'ok'],
                ['label' => 'Tasks today', 'value' => (string) ($fleet['tasks_today'] ?? 0), 'sub' => ($fleet['tasks_today_auto_pct'] ?? 96).'% auto', 'tone' => 'ok'],
                ['label' => 'Fleet miss-rate', 'value' => ($fleet['fleet_miss_rate_pct'] ?? 0.7).'%', 'sub' => 'ceiling '.$ceiling.'%', 'tone' => 'ok'],
                ['label' => 'Open alerts', 'value' => (string) ($fleet['open_alerts'] ?? 0), 'sub' => 'renewal agent watch', 'tone' => 'alert'],
                ['label' => 'Awaiting approval', 'value' => (string) $awaitingApproval, 'sub' => 'in your queue', 'tone' => 'alert'],
            ];
        }

        return [
            ['label' => 'Agents active', 'value' => (string) $active, 'sub' => 'of '.$this->registry->catalogCount().' in fleet', 'tone' => 'ok'],
            ['label' => 'Automation rate', 'value' => ($fleet['automation_rate_pct'] ?? 96).'%', 'sub' => 'tasks handled', 'tone' => 'ok'],
            ['label' => 'Miss-rate (fleet)', 'value' => ($fleet['fleet_miss_rate_pct'] ?? 0.7).'%', 'sub' => 'ceiling '.$ceiling.'%', 'tone' => 'ok'],
            ['label' => 'Awaiting your approval', 'value' => (string) $awaitingApproval, 'sub' => 'in Workflow Queues', 'tone' => 'alert'],
            ['label' => 'Hrs saved (est.)', 'value' => '~'.($fleet['hrs_saved_est'] ?? 410), 'sub' => 'this month', 'tone' => 'default'],
        ];
    }

    public function leaderboard(): array
    {
        return $this->agents()
            ->sortByDesc('tasks_may')
            ->map(fn (array $agent) => [
                'name' => $agent['short_name'],
                'slug' => $agent['slug'],
                'tasks' => number_format($agent['tasks_may'] ?? 0),
                'auto_pct' => ($agent['auto_handled_pct'] ?? 0).'%',
                'escalated' => $agent['escalated'] ?? 0,
                'miss_rate' => ($agent['miss_rate_pct'] ?? 0).'%',
                'miss_warn' => ($agent['miss_rate_pct'] ?? 0) >= 1.5,
                'status' => $agent['on_watch'] ? 'On watch' : ($agent['paused'] ? 'Paused' : 'Healthy'),
                'pill' => $agent['on_watch'] ? 'amber' : ($agent['paused'] ? 'gray' : 'green'),
            ])
            ->values()
            ->all();
    }

    public function missRateChart(): array
    {
        $weeks = config('staff_ai_agents.fleet.miss_rate_weeks', []);
        $ceiling = $this->ceiling();
        $max = max(array_merge([$ceiling, 1], $weeks));

        return collect($weeks)->values()->map(function ($rate, $index) use ($max) {
            return [
                'label' => 'W'.($index + 1),
                'rate' => $rate,
                'height_pct' => round(($rate / $max) * 100),
            ];
        })->all();
    }

    public function alerts(): array
    {
        $awaiting = app(WorkflowQueueService::class)->approvalCount(
            app(ApprovalQueueMetricsService::class)->approvalOrganizationId()
        );

        return collect(config('staff_ai_agents.alerts', []))
            ->map(function (array $alert) use ($awaiting) {
                if (str_contains($alert['title'] ?? '', 'awaiting your approval')) {
                    $alert['title'] = $awaiting.' item'.($awaiting === 1 ? '' : 's').' awaiting your approval';
                    $alert['link'] = route('workflow-queues');
                }

                return $alert;
            })
            ->all();
    }

    public function staffUsers(): Collection
    {
        $orgId = $this->organizationId();

        return User::query()
            ->with('roleModel')
            ->when($orgId, fn ($q) => $q->where(function ($inner) use ($orgId) {
                $inner->where('organization_id', $orgId);
                if (auth()->user()?->isSuperAdmin()) {
                    $inner->orWhereNull('organization_id');
                }
            }))
            ->where('role', '!=', User::ROLE_SUPER_ADMIN)
            ->where('role', '!=', User::ROLE_AI_AGENT)
            ->whereDoesntHave('aiAgent')
            ->orderBy('name')
            ->get();
    }

    public function staffKpis(): array
    {
        $users = $this->staffUsers();
        $owner = $users->first();
        $pendingInvites = $users->where('is_active', false)->whereNotNull('invite_token')->count();
        $require2fa = (bool) $this->globalSettings->get('security.require_2fa');
        $ownerInitials = $owner ? $this->initials($owner->name) : '—';

        return [
            ['label' => 'Human users', 'value' => (string) $users->count(), 'sub' => $users->count() === 1 ? 'you' : 'active accounts', 'tone' => 'default'],
            ['label' => 'Owner / approver', 'value' => $owner ? $this->shortName($owner->name) : '—', 'sub' => 'single approval gate', 'tone' => 'ok'],
            ['label' => 'Pending invites', 'value' => (string) $pendingInvites, 'sub' => $pendingInvites ? 'awaiting setup' : '—', 'tone' => 'default'],
            ['label' => '2FA enabled', 'value' => $require2fa ? 'Yes' : 'No', 'sub' => 'required', 'tone' => $require2fa ? 'ok' : 'alert'],
        ];
    }

    public function staffRoleLabel(User $user): string
    {
        return match ($user->role) {
            User::ROLE_ADMIN => 'Owner',
            User::ROLE_STAFF => 'Admin',
            User::ROLE_AI_AGENT => 'AI Agent',
            default => $user->role,
        };
    }

    public function staffPermissionsSummary(User $user): string
    {
        return match ($user->role) {
            User::ROLE_ADMIN => 'Full access · sole approver · agent config',
            User::ROLE_STAFF => 'Operations access · limited approvals',
            User::ROLE_AI_AGENT => 'Agent-scoped permissions · API runtime',
            default => 'Role-based permissions',
        };
    }

    public function userHas2fa(User $user): bool
    {
        return $user->two_factor_verified_at !== null;
    }

    public function userLastActive(User $user): string
    {
        if ($user->id === auth()->id()) {
            return 'Now';
        }

        return 'Recently';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function saveAgentConfig(string $slug, array $config): void
    {
        $agent = $this->agentModel($slug);
        if (! $agent) {
            return;
        }

        app(AiAgentRegistryService::class)->update($agent, [
            'autonomy_mode' => $config['autonomy_mode'] ?? $agent->autonomy_mode,
            'guardrails' => $config['guardrails'] ?? $agent->guardrails,
            'action_autonomy' => $config['action_autonomy'] ?? $agent->action_autonomy,
            'permission_slugs' => $config['permission_slugs'] ?? $agent->permission_slugs,
            'scope_programs' => $config['scope_programs'] ?? $agent->scope_programs,
            'scope_client_ids' => $config['scope_client_ids'] ?? $agent->scope_client_ids,
            'scope_location_ids' => $config['scope_location_ids'] ?? $agent->scope_location_ids,
            'credential_keys' => $config['credential_keys'] ?? $agent->credential_keys,
        ]);
    }

    public function pauseAgent(string $slug, bool $paused = true): void
    {
        $agent = $this->agentModel($slug);
        if ($agent) {
            app(AiAgentRegistryService::class)->setPaused($agent, $paused);
        }
    }

    protected function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return strtoupper(collect($parts)->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    }

    protected function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) < 2) {
            return $name;
        }

        return $parts[0].' '.mb_substr($parts[1], 0, 1).'.';
    }
}

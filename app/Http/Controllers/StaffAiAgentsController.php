<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffAiAgents\ImportAiAgentRequest;
use App\Http\Requests\StaffAiAgents\StoreAiAgentRequest;
use App\Http\Requests\StaffAiAgents\UpdateAiAgentRequest;
use App\Models\User;
use App\Services\AiAgentRegistryService;
use App\Services\AiAgentUserService;
use App\Services\StaffAiAgentsService;
use App\Support\TabbedPageTitle;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffAiAgentsController extends Controller
{
    public function __construct(
        protected StaffAiAgentsService $agents,
        protected AiAgentRegistryService $registry,
        protected AiAgentUserService $agentUsers,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $tab = $request->query('tab', 'agents');
        if (! in_array($tab, ['agents', 'operations', 'staff'], true)) {
            $tab = 'agents';
        }

        $agentCount = $this->agents->agents()->count();
        $staffCount = $this->agents->staffUsers()->count();
        $activeCount = $this->agents->activeAgentsCount();
        $ceiling = $this->agents->ceiling();

        return view('pages.staff-ai-agents.index', [
            'title' => TabbedPageTitle::staffAiAgents($tab),
            'tab' => $tab,
            'agentCount' => $agentCount,
            'staffCount' => $staffCount,
            'agents' => $this->agents->agents(),
            'fleetKpis' => $this->agents->fleetKpis($tab === 'operations' ? 'operations' : 'agents'),
            'staffKpis' => $this->agents->staffKpis(),
            'staffUsers' => $this->agents->staffUsers(),
            'leaderboard' => $this->agents->leaderboard(),
            'missRateChart' => $this->agents->missRateChart(),
            'alerts' => $this->agents->alerts(),
            'ceiling' => $this->agents->ceiling(),
            'subtitle' => match ($tab) {
                'operations' => 'AI Operations · live monitoring across all '.$agentCount.' agents',
                'staff' => 'Staff · human users with access to the platform',
                default => $activeCount.' agents running · '.$staffCount.' human'.($staffCount === 1 ? ' (you)' : 's').' · all within the '.$ceiling.'% miss-rate ceiling',
            },
        ]);
    }

    public function createAgent()
    {
        $this->authorize('viewAny', User::class);
        abort_unless(auth()->user()?->hasPermission('manage_ai_agents'), 403);

        $orgId = $this->agents->organizationId();

        return view('pages.staff-ai-agents.create', [
            'formOptions' => $this->registry->formOptions($orgId),
            'title' => 'Add AI Agent',
        ]);
    }

    public function storeAgent(StoreAiAgentRequest $request)
    {
        $orgId = $this->agents->organizationId();
        abort_unless($orgId, 403, 'Select an organization context to create agents.');

        $validated = $request->validated();
        $template = filled($validated['template_slug'] ?? null) ? $validated['template_slug'] : null;
        $catalog = $template ? config("staff_ai_agents.agents.{$template}", []) : [];

        $agent = $this->registry->create($orgId, [
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? null,
            'role_description' => $validated['role_description'] ?? ($catalog['role'] ?? null),
            'icon' => $validated['icon'] ?? ($catalog['icon'] ?? '🤖'),
            'icon_bg' => $catalog['icon_bg'] ?? 'bg-[#dbeafe]',
            'autonomy_mode' => $validated['autonomy_mode'],
            'scope_programs' => $validated['scope_programs'] ?? config('ai_agent_registry.programs', []),
            'scope_location_ids' => $validated['scope_location_ids'] ?? [],
            'scope_client_ids' => $validated['scope_client_ids'] ?? [],
            'credential_keys' => $validated['credential_keys'] ?? ($template ? config("ai_agent_registry.default_credentials.{$template}", []) : []),
            'permission_slugs' => $validated['permission_slugs'] ?? ($template ? config("ai_agent_registry.default_permissions.{$template}", ['view_dashboard']) : ['view_dashboard']),
            'catalog' => $catalog ? collect($catalog)->except(['slug'])->all() : [],
            'is_custom' => $template === null,
        ]);

        return redirect()
            ->route('staff.agents.show', $agent->slug)
            ->with('success', $agent->name.' created and platform user provisioned.');
    }

    public function importAgents(ImportAiAgentRequest $request)
    {
        $orgId = $this->agents->organizationId();
        abort_unless($orgId, 403, 'Select an organization context to import agents.');

        try {
            $imported = $this->registry->importFromJson($orgId, $request->file('import_file'));
        } catch (\JsonException|\InvalidArgumentException $e) {
            return back()->withErrors(['import_file' => $e->getMessage()]);
        }

        $count = count($imported);
        $label = $count === 1 ? $imported[0]->name : "{$count} agents";

        return redirect()
            ->route('staff.index', ['tab' => 'agents'])
            ->with('success', $label.' imported and platform users provisioned.');
    }

    public function exportAgents(): StreamedResponse
    {
        $this->authorize('viewAny', User::class);

        $orgId = $this->agents->organizationId();
        $payload = $this->registry->exportFleet($orgId);
        $filename = 'ai-agents-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            fn () => print(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    public function exportAgent(string $slug): StreamedResponse
    {
        $this->authorize('viewAny', User::class);

        $model = $this->registry->findBySlug($this->agents->organizationId(), $slug);
        abort_unless($model, 404);

        $payload = $this->registry->exportAgent($model);
        $filename = 'ai-agent-'.$slug.'-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            fn () => print(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    public function showAgent(string $slug)
    {
        $this->authorize('viewAny', User::class);

        $model = $this->registry->findBySlug($this->agents->organizationId(), $slug);
        abort_unless($model, 404);

        $agent = $this->registry->present($model);

        return view('pages.staff-ai-agents.agent-show', [
            'title' => trim((string) ($agent['name'] ?? '')) ?: 'AI Agent',
            'agent' => $agent,
            'agentModel' => $model,
            'ceiling' => $this->agents->ceiling(),
            'autonomyModes' => config('staff_ai_agents.autonomy_modes', []),
            'actionModes' => config('ai_agent_registry.action_modes', []),
            'actionDefinitions' => $this->registry->actionDefinitions($model),
            'formOptions' => $this->registry->formOptions($this->agents->organizationId()),
            'newApiToken' => session('agent_api_token'),
        ]);
    }

    public function updateAgent(UpdateAiAgentRequest $request, string $slug)
    {
        $model = $this->registry->findBySlug($this->agents->organizationId(), $slug);
        abort_unless($model, 404);

        $validated = $request->validated();

        $this->registry->update($model, [
            'name' => $validated['name'] ?? $model->name,
            'role_description' => $validated['role_description'] ?? $model->role_description,
            'autonomy_mode' => $validated['autonomy_mode'],
            'guardrails' => [
                'hold_on_gate_fail' => $validated['hold_on_gate_fail'] ?? ($model->guardrails['hold_on_gate_fail'] ?? true),
                'auto_resubmit' => $validated['auto_resubmit'] ?? ($model->guardrails['auto_resubmit'] ?? false),
                'approval_threshold' => (int) ($validated['approval_threshold'] ?? ($model->guardrails['approval_threshold'] ?? 0)),
            ],
            'action_autonomy' => $validated['action_autonomy'] ?? $model->action_autonomy,
            'scope_programs' => $validated['scope_programs'] ?? [],
            'scope_location_ids' => $validated['scope_location_ids'] ?? [],
            'scope_client_ids' => $validated['scope_client_ids'] ?? [],
            'credential_keys' => $validated['credential_keys'] ?? [],
            'permission_slugs' => $validated['permission_slugs'] ?? [],
        ]);

        return redirect()
            ->route('staff.agents.show', $slug)
            ->with('success', ($validated['name'] ?? $model->name).' configuration saved.');
    }

    public function pauseAgent(string $slug)
    {
        abort_unless(auth()->user()?->hasPermission('edit_staff'), 403);

        $model = $this->registry->findBySlug($this->agents->organizationId(), $slug);
        abort_unless($model, 404);

        $paused = ! $model->is_paused;
        $this->registry->setPaused($model, $paused);

        return redirect()
            ->route('staff.agents.show', $slug)
            ->with('success', ($paused ? 'Paused' : 'Resumed').' '.$model->name.'.');
    }

    public function toggleAgentEnabled(string $slug)
    {
        abort_unless(auth()->user()?->hasPermission('manage_ai_agents'), 403);

        $model = $this->registry->findBySlug($this->agents->organizationId(), $slug);
        abort_unless($model, 404);

        $enabled = ! $model->is_enabled;
        $this->registry->setEnabled($model, $enabled);

        return redirect()
            ->route('staff.agents.show', $slug)
            ->with('success', $model->name.($enabled ? ' enabled.' : ' disabled (kill switch).'));
    }

    public function regenerateToken(string $slug)
    {
        abort_unless(auth()->user()?->hasPermission('manage_ai_agents'), 403);

        $model = $this->registry->findBySlug($this->agents->organizationId(), $slug);
        abort_unless($model, 404);

        $token = $this->agentUsers->regenerateApiToken($model);

        return redirect()
            ->route('staff.agents.show', $slug)
            ->with('success', 'API token regenerated — copy it now; it will not be shown again.')
            ->with('agent_api_token', $token);
    }

    public function destroyAgent(string $slug)
    {
        abort_unless(auth()->user()?->hasPermission('manage_ai_agents'), 403);

        $model = $this->registry->findBySlug($this->agents->organizationId(), $slug);
        abort_unless($model, 404);

        $name = $model->name;
        $this->registry->delete($model);

        return redirect()
            ->route('staff.index', ['tab' => 'agents'])
            ->with('success', $name.' removed from the registry.');
    }
}

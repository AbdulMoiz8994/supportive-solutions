@extends('layouts.app')

@section('content')
@php
    $canEdit = auth()->user()?->hasPermission('edit_staff');
    $canManage = auth()->user()?->hasPermission('manage_ai_agents');
    $guardrails = $agent['guardrails'] ?? [];
    $selectedClients = collect($agent['scope_client_ids'] ?? []);
    $selectedLocations = collect($agent['scope_location_ids'] ?? []);
    $selectedPrograms = collect($agent['scope_programs'] ?? []);
    $selectedCreds = collect($agent['credential_keys'] ?? []);
    $selectedPerms = collect($agent['permission_slugs'] ?? []);
@endphp

<div class="space-y-6" x-data="{
    async confirmFormSubmit(event, options) {
        event.preventDefault();
        await this.$store.dialog.confirmSubmit(event.target, options);
    },
}">
    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif
    @if($newApiToken ?? false)
        <div class="rounded-xl border border-[#fde68a] bg-[#fffbeb] px-4 py-3 text-sm">
            <p class="font-semibold text-[#92400e]">New API token (copy now):</p>
            <code class="block mt-2 text-xs break-all text-[#78350f]">{{ $newApiToken }}</code>
        </div>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route('staff.index') }}" class="text-[13px] text-[#2563eb] font-semibold hover:underline">‹ AI Agents</a>
            <div class="flex flex-wrap items-center gap-2 mt-1">
                <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight">{{ $agent['name'] }}</h1>
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11.5px] font-semibold bg-[#f1f5f9] text-[#475569]">
                    {{ $agent['autonomy_label'] }} · {{ strtolower($agent['status_label']) }}
                </span>
            </div>
            <p class="text-[13px] text-[#64748b] mt-1.5">{{ $agent['summary'] ?? $agent['role'] }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.btn href="{{ route('staff.agents.export.single', $agent['slug']) }}" variant="outline">Export JSON</x-ui.btn>
            @if($canManage)
                <form action="{{ route('staff.agents.enable', $agent['slug']) }}" method="POST">
                    @csrf
                    <x-ui.btn type="submit" variant="outline">{{ ($agent['is_enabled'] ?? true) ? 'Disable (kill switch)' : 'Enable agent' }}</x-ui.btn>
                </form>
            @endif
            @if($canEdit)
                <form action="{{ route('staff.agents.pause', $agent['slug']) }}" method="POST">
                    @csrf
                    <x-ui.btn type="submit" variant="outline">{{ ($agent['paused'] ?? false) ? '▶ Resume' : '⏸ Pause' }}</x-ui.btn>
                </form>
                <x-ui.btn type="submit" form="agent-config-form" variant="primary">Save config</x-ui.btn>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[1fr_360px] gap-4">
        <div class="space-y-4">
            @if(!empty($agent['what_it_does']))
            <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
                <h4 class="text-[12px] font-bold uppercase tracking-wide text-[#2563eb] mb-3">What this agent does</h4>
                <dl class="grid grid-cols-1 sm:grid-cols-[180px_1fr] gap-x-4 gap-y-2.5 text-[13px]">
                    @foreach($agent['what_it_does'] as $key => $value)
                        <dt class="text-[#94a3b8]">{{ $key }}</dt>
                        <dd class="text-[#0f172a] font-medium">{{ $value }}</dd>
                    @endforeach
                </dl>
            </div>
            @endif

            @if($canEdit)
            <form id="agent-config-form" action="{{ route('staff.agents.update', $agent['slug']) }}" method="POST" class="space-y-4">
                @csrf

                <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
                    <h4 class="text-[12px] font-bold uppercase tracking-wide text-[#2563eb] mb-3">Scope</h4>
                    <div class="space-y-4">
                        <div>
                            <p class="text-[12px] font-semibold text-[#0f172a] mb-2">Programs</p>
                            <div class="flex flex-wrap gap-3">
                                @foreach($formOptions['programs'] as $program)
                                    <label class="inline-flex items-center gap-2 text-[13px]">
                                        <input type="checkbox" name="scope_programs[]" value="{{ $program }}"
                                               @checked($selectedPrograms->contains($program)) class="rounded border-[#cbd5e1]">
                                        {{ $program }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @if($formOptions['locations']->isNotEmpty())
                            @include('pages.staff-ai-agents.partials.scope-select', [
                                'name' => 'scope_location_ids',
                                'label' => 'Locations',
                                'hint' => '(none = all)',
                                'options' => $formOptions['locations']->pluck('name', 'id'),
                                'selected' => old('scope_location_ids', $selectedLocations->all()),
                                'placeholder' => 'Search locations…',
                            ])
                        @endif
                        @if($formOptions['clients']->isNotEmpty())
                            @include('pages.staff-ai-agents.partials.scope-select', [
                                'name' => 'scope_client_ids',
                                'label' => 'Clients',
                                'hint' => '(none = all clients in scope)',
                                'options' => $formOptions['clients']->mapWithKeys(fn ($c) => [$c->id => trim($c->first_name.' '.$c->last_name)]),
                                'selected' => old('scope_client_ids', $selectedClients->all()),
                                'placeholder' => 'Search clients…',
                            ])
                        @endif
                    </div>
                </div>

                <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
                    <h4 class="text-[12px] font-bold uppercase tracking-wide text-[#2563eb] mb-3">Autonomy per action</h4>
                    @if(count($actionDefinitions) === 0)
                        <p class="text-[13px] text-[#64748b]">No action catalog for this agent type — set default autonomy below.</p>
                    @else
                        <div class="divide-y divide-[#f1f5f9]">
                            @foreach($actionDefinitions as $i => $action)
                                <input type="hidden" name="action_autonomy[{{ $i }}][key]" value="{{ $action['key'] }}">
                                <div class="flex items-center justify-between py-2.5 gap-4">
                                    <span class="text-[13px] font-medium text-[#0f172a]">{{ $action['label'] }}</span>
                                    <select name="action_autonomy[{{ $i }}][mode]" class="text-[12px] border border-[#e2e8f0] rounded-lg px-2 py-1">
                                        @foreach($actionModes as $mode => $label)
                                            <option value="{{ $mode }}" @selected($action['mode'] === $mode)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
                    <h4 class="text-[12px] font-bold uppercase tracking-wide text-[#2563eb] mb-3">Autonomy &amp; guardrails</h4>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 py-2.5 border-b border-[#f1f5f9]">
                        <div>
                            <p class="font-semibold text-[#0f172a] text-[13px]">Default autonomy level</p>
                        </div>
                        @include('pages.staff-ai-agents.partials.autonomy-mode-picker', [
                            'modes' => $autonomyModes,
                            'name' => 'autonomy_mode',
                            'value' => old('autonomy_mode', $agent['autonomy_mode']),
                        ])
                    </div>
                    @include('pages.staff-ai-agents.partials.guardrail-toggles', ['guardrails' => $guardrails, 'ceiling' => $ceiling])
                </div>

                <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
                    <h4 class="text-[12px] font-bold uppercase tracking-wide text-[#2563eb] mb-3">Credentials</h4>
                    <p class="text-[11px] text-[#94a3b8] mb-3">Vault keys this agent may use at runtime. Manage secrets in <a href="{{ route('settings.global', ['tab' => 'credential-vault']) }}" class="text-[#2563eb] font-semibold">Credential Vault</a>.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($formOptions['credentials'] as $cred)
                            <label class="inline-flex items-center gap-2 text-[13px]">
                                <input type="checkbox" name="credential_keys[]" value="{{ $cred['key'] }}"
                                       @checked($selectedCreds->contains($cred['key'])) class="rounded border-[#cbd5e1]">
                                {{ $cred['label'] }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
                    <h4 class="text-[12px] font-bold uppercase tracking-wide text-[#2563eb] mb-3">Platform role &amp; permissions</h4>
                    <p class="text-[11px] text-[#94a3b8] mb-3">This agent acts as a platform user with the same permission slugs as staff roles.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 max-h-48 overflow-y-auto">
                        @foreach($formOptions['permissions']->groupBy('module') as $module => $perms)
                            <div class="col-span-full text-[10px] font-bold uppercase text-[#94a3b8] mt-2 first:mt-0">{{ $module }}</div>
                            @foreach($perms as $perm)
                                <label class="inline-flex items-center gap-2 text-[12px] py-0.5">
                                    <input type="checkbox" name="permission_slugs[]" value="{{ $perm->slug }}"
                                           @checked($selectedPerms->contains($perm->slug)) class="rounded border-[#cbd5e1]">
                                    {{ $perm->name }}
                                </label>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </form>
            @endif

            @if(!empty($agent['run_log']))
            <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
                <h4 class="text-[12px] font-bold uppercase tracking-wide text-[#2563eb] mb-3">Recent run log</h4>
                @foreach($agent['run_log'] as $entry)
                    <div class="flex gap-2.5 text-[12.5px] mb-2">
                        <span class="w-2 h-2 rounded-full {{ ($entry['tone'] ?? '') === 'esc' ? 'bg-[#f59e0b]' : 'bg-[#10b981]' }} mt-1.5 shrink-0"></span>
                        <div>
                            <p class="font-semibold text-[#0f172a]">{{ $entry['title'] }} <span class="font-normal">{{ $entry['detail'] ?? '' }}</span></p>
                            <p class="text-[11px] text-[#94a3b8]">{{ $entry['time'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
                <h4 class="text-[12px] font-bold uppercase tracking-wide text-[#64748b] mb-2.5">Agent login (platform user)</h4>
                <dl class="space-y-2 text-[12.5px]">
                    <div class="flex justify-between gap-2"><dt class="text-[#94a3b8]">Email</dt><dd class="font-mono text-[11px] text-[#0f172a] truncate">{{ $agent['user_email'] ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#94a3b8]">Role</dt><dd class="font-semibold">AI Agent</dd></div>
                    <div class="flex justify-between"><dt class="text-[#94a3b8]">Status</dt><dd>{{ ($agent['user_active'] ?? false) ? 'Active' : 'Inactive' }}</dd></div>
                </dl>
                @if($canManage)
                    <form action="{{ route('staff.agents.token', $agent['slug']) }}" method="POST" class="mt-3">
                        @csrf
                        <x-ui.btn type="submit" variant="outline" class="w-full justify-center text-[12px]">Regenerate API token</x-ui.btn>
                    </form>
                @endif
            </div>

            @include('pages.staff-ai-agents.partials.agent-performance-sidebar', ['agent' => $agent])

            @if($canManage && ($agent['is_custom'] ?? false))
                <form action="{{ route('staff.agents.destroy', $agent['slug']) }}" method="POST"
                      @submit.prevent="confirmFormSubmit($event, {
                          title: 'Delete this AI agent?',
                          message: 'This removes the agent and its platform user. This cannot be undone.',
                          confirmLabel: 'Delete agent',
                          variant: 'danger',
                      })">
                    @csrf
                    @method('DELETE')
                    <x-ui.btn type="submit" variant="outline" class="w-full text-red-600 border-red-200">Delete agent</x-ui.btn>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection

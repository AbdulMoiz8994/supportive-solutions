@extends('layouts.app')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif

    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight">Staff &amp; AI Agents</h1>
            <p class="text-[13px] text-[#64748b] mt-1.5">{{ $subtitle }}</p>
        </div>
        <div class="flex items-center flex-nowrap gap-3 shrink-0">
            @if($tab === 'staff')
                @can('create', \App\Models\User::class)
                    <x-ui.btn href="{{ route('staff.create') }}" variant="primary">+ Add Staff</x-ui.btn>
                @endcan
            @elseif($tab === 'operations')
                <x-ui.btn variant="outline" disabled title="Coming soon">Export</x-ui.btn>
                <x-ui.btn variant="outline" disabled title="Coming soon">Alert settings</x-ui.btn>
            @else
                <a href="{{ route('staff.agents.export') }}"
                   class="inline-flex items-center h-9 text-[13px] font-semibold text-[#2563eb] hover:underline whitespace-nowrap">
                    Export JSON
                </a>
                @if(auth()->user()?->hasPermission('manage_ai_agents'))
                    <form action="{{ route('staff.agents.import') }}" method="POST" enctype="multipart/form-data" class="inline-flex items-center">
                        @csrf
                        <label class="inline-flex items-center h-9 px-3 text-[13px] font-semibold text-[#2563eb] border border-[#bfdbfe] rounded-lg cursor-pointer hover:bg-[#eff6ff] whitespace-nowrap">
                            Import JSON
                            <input type="file" name="import_file" accept=".json,application/json" class="sr-only"
                                   onchange="if(this.files.length) this.form.submit()">
                        </label>
                    </form>
                    <x-ui.btn href="{{ route('staff.agents.create') }}" variant="primary">+ Add agent</x-ui.btn>
                @else
                    <x-ui.btn variant="primary" disabled title="Requires manage_ai_agents permission">+ Add agent</x-ui.btn>
                @endif
            @endif
        </div>
    </div>

    @include('pages.staff-ai-agents.partials.subtabs', [
        'tab' => $tab,
        'agentCount' => $agentCount,
        'staffCount' => $staffCount,
    ])

    @if($tab === 'staff')
        @include('pages.reports.partials.kpi-row', ['kpis' => $staffKpis, 'cols' => 4])
    @else
        @include('pages.reports.partials.kpi-row', ['kpis' => $fleetKpis, 'cols' => 5])
    @endif

    @if($tab === 'agents')
        @include('pages.staff-ai-agents.partials.agents-tab', [
            'agents' => $agents,
        ])
    @elseif($tab === 'operations')
        @include('pages.staff-ai-agents.partials.operations-tab', [
            'missRateChart' => $missRateChart,
            'alerts' => $alerts,
            'leaderboard' => $leaderboard,
            'ceiling' => $ceiling,
        ])
    @else
        @include('pages.staff-ai-agents.partials.staff-tab', [
            'staffUsers' => $staffUsers,
        ])
    @endif
</div>
@endsection

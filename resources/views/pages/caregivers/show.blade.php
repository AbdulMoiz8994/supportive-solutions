@extends('layouts.app')

@section('content')
@php
    use App\Support\TabbedPageTitle;

    $c = $caregiver;
    $tabs = TabbedPageTitle::CAREGIVER_TAB_LABELS;
    $allClear = $c->backgroundChecks->whereIn('status', ['Flagged'])->isEmpty()
                && $c->backgroundChecks->whereIn('status', ['Enrolling', 'Submitted'])->isEmpty()
                && $c->backgroundChecks->isNotEmpty();
@endphp

<div class="w-full px-2 pb-20" x-data="{
    tab: new URLSearchParams(window.location.search).get('tab') || '{{ request('tab', 'personal') }}',
    tabs: @js($tabs),
    contextName: @js($c->name),
    appName: @js(config('app.name', 'beydountech Home Care')),
    switchTab(key) {
        this.tab = key;
        history.replaceState(null, '', '?tab=' + key);
        this.syncTitle();
    },
    syncTitle() {
        const label = this.tabs[this.tab] || 'Caregiver Details';
        document.title = label + ' — ' + this.contextName + ' | ' + this.appName;
    },
    init() { this.syncTitle(); }
}">

    @if(session('success'))
    <div class="mb-5 flex items-center gap-3 rounded-xl bg-green-50 border border-green-100 px-4 py-3 text-[12px] font-bold text-green-700"
        x-data x-init="setTimeout(() => $el.remove(), 5000)">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif

    {{-- Breadcrumb --}}
    <div class="text-[12px] font-semibold text-[#64748b] pt-2 pb-3">
        <a href="{{ route('caregivers') }}" class="text-blue-600 hover:underline">Caregivers</a>
        <span class="mx-1">›</span> {{ $c->name }}
    </div>

    {{-- Header card --}}
    <div class="bg-[#eff6ff] rounded-[24px] border border-blue-100/50 shadow-sm p-6 mb-4">
        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-5">
            <div class="flex items-start gap-4">
                <img src="https://ui-avatars.com/api/?name={{ urlencode($c->name) }}&background=2563eb&color=fff&bold=true&size=128" class="w-16 h-16 rounded-full shadow">
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-[24px] font-black text-[#1e293b] leading-tight">{{ $c->name }}</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $c->status === 'Active' ? 'bg-green-100 text-green-700' : ($c->status === 'On Hold' ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700') }}">{{ $c->onboarding_status === 'Pending onboarding' ? 'Pending' : $c->status }}</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 text-violet-700">{{ $assignment->program ?? 'MICH' }}</span>
                        @if($c->live_in)<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700">Live-in</span>@endif
                    </div>
                    <div class="mt-2 space-y-1 text-[12px] text-[#475569]">
                        <p>
                            <b>DOB</b> {{ $c->date_of_birth?->format('m/d/Y') ?? '—' }} · Age {{ $c->date_of_birth?->age ?? '—' }}
                            @if($servedClient)<span class="ml-2"><b>Serves</b> {{ $servedClient->first_name }} {{ $servedClient->last_name }} <span class="text-[#94a3b8]">({{ $assignment->relationship }} · {{ $assignment->program ?? 'MICH' }})</span></span>@endif
                            <span class="ml-2"><b>Employment</b> {{ $c->pay_type ?? 'W-2' }} · ${{ number_format((float)($c->hourly_wage ?? 0), 2) }}/hr</span>
                        </p>
                        <p>
                            <b>CHAMPS Provider ID</b> <span class="text-[#94a3b8]">{{ $c->champs_provider_id ?? '—' }}</span>
                            <span class="ml-3"><b>Checks</b> <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $allClear ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' }}">{{ $allClear ? 'All clear' : 'In progress' }}</span></span>
                            <span class="ml-3"><b>County</b> <span class="text-[#94a3b8]">{{ $c->county ?? '—' }}</span></span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="$dispatch('open-ai-panel')" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-[#2563eb]/30 bg-[#eff4ff] text-[#2563eb] text-[12px] font-bold hover:bg-[#dbe6ff] transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1a7 7 0 0 1-7 7H10a7 7 0 0 1-7-7H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73A2 2 0 0 1 12 2z"/><circle cx="8.5" cy="14" r="1"/><circle cx="15.5" cy="14" r="1"/></svg>
                    AI summary
                </button>
                <button class="px-4 py-2 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-bold text-[#475569] shadow-sm hover:bg-gray-50">Scan Doc</button>
                <a href="{{ route('messages.index') }}" class="px-4 py-2 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-bold text-[#475569] shadow-sm hover:bg-gray-50">Message</a>
                <button @click="switchTab('assignments')" class="px-4 py-2 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-bold text-[#475569] shadow-sm hover:bg-gray-50">View assignments</button>
                <button @click="switchTab('personal'); $dispatch('edit-personal')" class="px-5 py-2 bg-[#2563eb] text-white rounded-xl text-[12px] font-bold shadow-lg shadow-blue-100 hover:bg-[#1d4ed8]">Edit</button>
            </div>
        </div>

        {{-- Compliance alerts + authorized load (backend automation) --}}
        <div class="mt-4 flex flex-wrap items-center gap-2">
            @forelse($c->credential_alerts as $alert)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold border
                    {{ $alert['tone'] === 'red' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-amber-50 text-amber-700 border-amber-200' }}">
                    ⚠ {{ $alert['label'] }}
                </span>
            @empty
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-green-50 text-green-700 border border-green-200">✓ Credentials clear</span>
            @endforelse
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                Authorized load: {{ $c->total_weekly_hours }} hrs/wk · {{ $c->total_daily_hours }} hrs/day across {{ $c->assigned_client_count }} {{ Str::plural('client', $c->assigned_client_count) }}
            </span>
        </div>
    </div>

    {{-- Live-in ribbon --}}
    @if($c->live_in)
    <div class="bg-green-50 border border-green-200 rounded-2xl px-5 py-3.5 mb-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-[12.5px] font-semibold text-green-700">Live-in caregiver — EVV exempt. Live-In Attestation (BPHASA-2421) {{ $c->attestation_status ?? 'approved' }}. No HHAeXchange clock-in/out required.</p>
        </div>
        <button class="px-4 py-1.5 bg-white border border-green-200 rounded-lg text-[11px] font-bold text-green-700 shrink-0">View attestation</button>
    </div>
    @endif

    {{-- Tab nav --}}
    <div class="bg-white rounded-t-[20px] border border-blue-100/50 border-b-0 px-4">
        <nav class="flex gap-6 overflow-x-auto no-scrollbar">
            @foreach($tabs as $key => $label)
                <button @click="switchTab('{{ $key }}')"
                    :class="tab==='{{ $key }}' ? 'border-[#2563eb] text-[#1e293b]' : 'border-transparent text-[#94a3b8] hover:text-[#475569]'"
                    class="py-4 text-[12.5px] font-bold whitespace-nowrap border-b-2 transition-all">{{ $label }}</button>
            @endforeach
        </nav>
    </div>

    {{-- Tab panels --}}
    <div class="bg-[#eff6ff] rounded-b-[20px] border border-blue-100/50 p-6 min-h-[500px]">
        <div x-show="tab==='personal'" x-cloak>@include('pages.caregivers.tabs.personal')</div>
        <div x-show="tab==='onboarding'" x-cloak>@include('pages.caregivers.tabs.onboarding')</div>
        <div x-show="tab==='checks'" x-cloak>@include('pages.caregivers.tabs.checks')</div>
        <div x-show="tab==='assignments'" x-cloak>@include('pages.caregivers.tabs.assignments')</div>
        <div x-show="tab==='schedule'" x-cloak>@include('pages.caregivers.tabs.schedule')</div>
        <div x-show="tab==='access'" x-cloak>@include('pages.caregivers.tabs.access')</div>
        <div x-show="tab==='compliance'" x-cloak>@include('pages.caregivers.tabs.compliance')</div>
        <div x-show="tab==='pay'" x-cloak>@include('pages.caregivers.tabs.pay')</div>
        <div x-show="tab==='documents'" x-cloak>@include('pages.caregivers.tabs.documents')</div>
        <div x-show="tab==='communications'" x-cloak>@include('pages.caregivers.tabs.communications')</div>
        <div x-show="tab==='notes'" x-cloak>@include('pages.caregivers.tabs.notes')</div>
        <div x-show="tab==='audit'" x-cloak>@include('pages.caregivers.tabs.audit')</div>
    </div>

    {{-- ── AI brief panel (Claude case summary) ───────────────────────────── --}}
    <x-ai.summary-panel :url="route('ai.caregiver-summary', $c->id)" title="Caregiver brief — {{ $c->name }}" />
</div>

<style>[x-cloak]{display:none!important}</style>
@include('partials.google-maps-autocomplete')
@endsection

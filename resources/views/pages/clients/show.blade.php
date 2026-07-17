@extends('layouts.app')

@php
    use App\Support\TabbedPageTitle;

    $updateUrl = route('clients.update', $client->id);
    $age = $client->age;
    $program = $client->program_label;
    $auth = $client->authStatus();
    $authDetail = $client->currentAuthorization();
    $caregiver = $client->primary_caregiver;
    $coordinator = $client->caseCoordinator();
    $emergency = $client->emergencyContact();
    $statusName = $client->statusRecord?->name ?? $client->status ?? 'Active';
    $statusTone = $client->status_tone;
    $statusSinceFormatted = $statusSince ? \Carbon\Carbon::parse($statusSince)->format('M j, Y') : null;

    $tabs = TabbedPageTitle::CLIENT_TAB_LABELS;
    $clientDisplayName = trim($client->first_name.' '.$client->last_name);
@endphp

@section('content')
<div class="max-w-full mx-auto pb-12" x-data="{
    activeTab: new URLSearchParams(window.location.search).get('tab') || 'demographics',
    sendRequestOpen: false,
    changeStatusOpen: false,
    tabs: @js($tabs),
    contextName: @js($clientDisplayName),
    appName: @js(config('app.name', 'beydountech Home Care')),
    switchTab(key) {
        this.activeTab = key;
        history.replaceState(null, '', '?tab=' + key);
        this.syncTitle();
    },
    syncTitle() {
        const label = this.tabs[this.activeTab] || 'Client Details';
        document.title = label + ' — ' + this.contextName + ' | ' + this.appName;
    },
    init() { this.syncTitle(); }
}">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-1.5 text-sm font-medium mb-3">
        <a href="{{ route('clients.index') }}" class="text-[#2563eb] hover:text-[#1d4ed8]">Clients</a>
        <span class="text-[#cbd5e1]">›</span>
        <span class="text-[#64748b]">{{ $client->first_name }} {{ $client->last_name }}</span>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div x-data="{show:true}" x-show="show" x-transition class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-[#d1fadf] bg-[#ecfdf3] px-4 py-3 text-sm font-semibold text-[#067647]">
            <span>{{ session('success') }}</span>
            <button @click="show=false" class="text-[#067647]/60 hover:text-[#067647]">&times;</button>
        </div>
    @endif
    @if(session('error'))
        <div x-data="{show:true}" x-show="show" x-transition class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-[#fee4e2] bg-[#fef3f2] px-4 py-3 text-sm font-semibold text-[#d92d20]">
            <span>{{ session('error') }}</span>
            <button @click="show=false" class="text-[#d92d20]/60 hover:text-[#d92d20]">&times;</button>
        </div>
    @endif

    {{-- ── Header card ────────────────────────────────────────────────────── --}}
    <div class="rounded-2xl border border-card-border bg-card p-6 mb-4">
        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-5">
            <div class="flex items-start gap-4">
                <div class="w-[68px] h-[68px] rounded-full overflow-hidden shrink-0 border-2 border-white shadow-sm">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($client->first_name.' '.$client->last_name) }}&background=2563eb&color=fff&bold=true&size=128" class="w-full h-full object-cover" alt="">
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-xl font-extrabold text-[#0f172a] tracking-tight">{{ $client->first_name }} {{ $client->last_name }}</h1>
                        <button type="button" @click="changeStatusOpen = true" class="group inline-flex items-center gap-1.5 font-semibold rounded-full border whitespace-nowrap text-[11px] px-2.5 py-0.5 transition-all hover:ring-2 hover:ring-offset-1
                            @if($statusTone === 'green') bg-[#ecfdf3] text-[#067647] border-[#d1fadf] hover:ring-[#d1fadf]
                            @elseif($statusTone === 'amber') bg-[#fff8eb] text-[#b54708] border-[#fdecc8] hover:ring-[#fdecc8]
                            @elseif($statusTone === 'red') bg-[#fef3f2] text-[#d92d20] border-[#fee4e2] hover:ring-[#fee4e2]
                            @elseif($statusTone === 'blue') bg-[#eff4ff] text-[#2563eb] border-[#dbe6ff] hover:ring-[#dbe6ff]
                            @else bg-[#f1f5f9] text-[#475569] border-[#e2e8f0] hover:ring-[#e2e8f0]
                            @endif">
                            {{ $statusName }}
                            <svg class="w-3 h-3 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <x-ui.pill :variant="$program === 'DHS' ? 'indigo' : 'blue'">{{ $program }}</x-ui.pill>
                        @if($auth['tone'] === 'amber')
                            <x-ui.pill variant="amber">PA expires in {{ $auth['days'] }} days</x-ui.pill>
                        @elseif($auth['tone'] === 'red')
                            <x-ui.pill variant="red">PA expired</x-ui.pill>
                        @endif
                        @if($client->status_needs_attention)
                            <x-ui.pill variant="red" title="Stuck in {{ $statusName }} for {{ $client->days_in_current_status }} days">⚠ Stuck {{ $client->days_in_current_status }}d in {{ $statusName }}</x-ui.pill>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1 mt-2 text-sm text-[#475569]">
                        <span><span class="font-semibold text-[#0f172a]">DOB</span> {{ $client->dob ? \Carbon\Carbon::parse($client->dob)->format('m/d/Y') : '—' }}@if($age) · Age {{ $age }}@endif</span>
                        <span><span class="font-semibold text-[#0f172a]">Medicaid</span> ••••••{{ $client->member_id ? substr($client->member_id, -4) : '—' }} · dual eligible</span>
                        <span><span class="font-semibold text-[#0f172a]">MCO</span> {{ $client->mco_name ?? '—' }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1 mt-1 text-sm text-[#475569]">
                        <span><span class="font-semibold text-[#0f172a]">Case Coordinator</span> {{ $coordinator?->name ?? '—' }}</span>
                        <span><span class="font-semibold text-[#0f172a]">Caregiver</span> {{ $caregiver ? $caregiver->first_name.' '.$caregiver->last_name : 'Unassigned' }}</span>
                        <span><span class="font-semibold text-[#0f172a]">County</span> {{ $client->county ?? '—' }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap shrink-0">
                <x-ui.btn variant="outline" size="sm" x-on:click="switchTab('documents')">Scan Doc</x-ui.btn>
                @if($canSendRequest)
                    <x-ui.btn variant="outline" size="sm" x-on:click="sendRequestOpen = true">Message</x-ui.btn>
                @endif
                <x-ui.btn variant="outline" size="sm" x-on:click="switchTab('authorization')">View Authorization</x-ui.btn>
                <x-ui.btn variant="primary" size="sm" x-on:click="$dispatch('open-ai-panel')">Daily brief</x-ui.btn>
            </div>
        </div>
    </div>

    {{-- ── Authorization alert banner ──────────────────────────────────────── --}}
    @if($auth['tone'] === 'amber' || $auth['tone'] === 'red')
        <div class="rounded-2xl border border-[#fdecc8] bg-[#fffaf0] px-5 py-3.5 mb-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-[#b54708] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <p class="text-sm text-[#92400e] leading-relaxed">
                    @if($program === 'DHS')
                        @if($auth['days'] !== null && $auth['days'] < 0)
                            <span class="font-bold">DHS Time/Task reassessment is overdue.</span> Service continues — schedule the 6-month review; there is no PA expiry for Home Help.
                        @else
                            <span class="font-bold">DHS Time/Task reassessment due {{ $authDetail?->end_date ? \Carbon\Carbon::parse($authDetail->end_date)->format('F j, Y') : '' }}@if($auth['days'] !== null) ({{ $auth['days'] }} days)@endif.</span>
                            Reassessment every 6 months — not a prior-auth renewal.
                        @endif
                    @elseif($auth['tone'] === 'red')
                        <span class="font-bold">Prior Authorization has expired.</span> Service is paused until a new PA arrives — no billing on an expired authorization.
                    @else
                        <span class="font-bold">Prior Authorization expires {{ $authDetail?->end_date ? \Carbon\Carbon::parse($authDetail->end_date)->format('F j, Y') : '' }} ({{ $auth['days'] }} days).</span>
                        Renewal is due 2 weeks before PA end — the Authorizations agent has it queued.
                    @endif
                </p>
            </div>
            <x-ui.btn variant="outline" size="sm" x-on:click="switchTab('authorization')">Go to Authorization</x-ui.btn>
        </div>
    @endif

    {{-- ── Tab navigation ─────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-1 overflow-x-auto no-scrollbar border-b border-[#e6eef9] mb-5">
        @foreach($tabs as $key => $label)
            <button x-on:click="switchTab('{{ $key }}')"
                :class="activeTab === '{{ $key }}' ? 'text-[#0f172a] border-[#2563eb]' : 'text-[#94a3b8] border-transparent hover:text-[#475569]'"
                class="whitespace-nowrap px-3.5 py-3 text-sm font-semibold border-b-2 transition-colors -mb-px">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ── Tab panels ─────────────────────────────────────────────────────── --}}
    @include('pages.clients.tabs.demographics')
    @include('pages.clients.tabs.intake')
    @include('pages.clients.tabs.authorization')
    @include('pages.clients.tabs.caregiver')
    @include('pages.clients.tabs.compliance')
    @include('pages.clients.tabs.billing')
    @include('pages.clients.tabs.documents')
    @include('pages.clients.tabs.communications')
    @include('pages.clients.tabs.schedule')
    @include('pages.clients.tabs.notes')
    @include('pages.clients.tabs.audit')

    {{-- ── Change Status modal ───────────────────────────────────────────── --}}
    <template x-teleport="body">
        <div x-show="changeStatusOpen" x-cloak class="fixed inset-0 z-[999999] flex items-center justify-center p-4"
             x-data="{
                 selected: null,
                 reasons: {
                     'Discharged': ['Moved out of service area','Entered facility','Lost eligibility','Client request'],
                     'Denied':     ['Determination denied','Ineligible','Program criteria not met','Administrative denial'],
                     'On Hold':    ['Authorization expired','Care paused','Traveling','Client requested hold'],
                     'Recovery':   ['Hospitalized','Short-term rehab','Expected to return'],
                     'Pending':    ['Re-enrollment','Awaiting DHS/MICH determination'],
                     'Active':     ['Care resumed','Recovery complete','Authorization approved'],
                     'Deceased':   []
                 },
                 effects: {
                     'Discharged': [
                         'Billing stops — no new claims after the last service date.',
                         '{{ $caregiver ? $caregiver->first_name." ".$caregiver->last_name."\'s" : "The caregiver\'s" }} assignment to this client is ended (they stay Active for other clients).',
                         '{{ $client->first_name }} drops off the active roster and this month\'s invoice sheet.',
                         'Open authorization is closed; renewal queue item is cancelled.',
                         'Chart is retained read-only and archived — nothing is deleted.'
                     ],
                     'Deceased': [
                         'Billing stops — no new claims after the date of death.',
                         '{{ $caregiver ? $caregiver->first_name." ".$caregiver->last_name."\'s" : "The caregiver\'s" }} assignment is ended immediately.',
                         '{{ $client->first_name }} drops off the active roster.',
                         'A death record is filed; the chart is archived read-only.'
                     ],
                     'Denied': [
                         'No care is authorized.',
                         'This case escalates with the denial reason attached.',
                         'A denial notice should be sent to the client.'
                     ],
                     'On Hold': [
                         'Billing pauses — no new claims while on hold.',
                         '{{ $caregiver ? $caregiver->first_name." ".$caregiver->last_name : "The caregiver" }} keeps the assignment but no new visits are billed.',
                         '{{ $client->first_name }} stays on the active roster flagged as On Hold.'
                     ]
                 },
                 dotColor: {
                     'Pending':    '#94a3b8',
                     'Active':     '#16a34a',
                     'On Hold':    '#d97706',
                     'Recovery':   '#2563eb',
                     'Discharged': '#dc2626',
                     'Deceased':   '#94a3b8',
                     'Denied':     '#dc2626'
                 },
                 get actionLabel() {
                     const m = {'Discharged':'Confirm Discharge','Denied':'Confirm Denial','Deceased':'Mark as Deceased','On Hold':'Place on Hold','Recovery':'Move to Recovery','Pending':'Move to Pending','Active':'Activate'};
                     return m[this.selected] || 'Confirm Change';
                 },
                 get showLastServiceDate() { return ['Discharged','Deceased'].includes(this.selected); },
                 get showEffects()         { return this.effects[this.selected]?.length > 0; },
                 get effectsClass()        { return ['Discharged','Deceased'].includes(this.selected) ? 'border-[#fecaca] bg-[#fef2f2] text-[#b42318]' : 'border-[#fdecc8] bg-[#fff8eb] text-[#92400e]'; },
                 get effectsIcon()         { return ['Discharged','Deceased'].includes(this.selected) ? '#dc2626' : '#d97706'; }
             }">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="changeStatusOpen = false; selected = null"></div>
            <div class="relative w-full max-w-xl bg-white rounded-[20px] shadow-2xl overflow-hidden max-h-[90vh] flex flex-col" @click.stop>

                {{-- Header --}}
                <div class="px-7 py-5 border-b border-[#eef2f9] flex justify-between items-start shrink-0">
                    <div>
                        <h3 class="text-lg font-bold text-[#0f172a]">Change Status — {{ $client->first_name }} {{ $client->last_name }}</h3>
                        <p class="text-sm text-[#64748b] mt-0.5">Client
                            @if($program !== '—') · {{ $program }}@endif
                            @if($coordinator?->name) · {{ $coordinator->name }}@endif
                            @if($caregiver)
                                · Caregiver: {{ $caregiver->first_name }} {{ $caregiver->last_name }}@if($client->lives_with_caregiver) (live-in)@endif
                            @endif
                        </p>
                    </div>
                    <button type="button" @click="changeStatusOpen = false; selected = null"
                        class="w-8 h-8 rounded-full border border-[#eef2f9] flex items-center justify-center text-[#94a3b8] hover:bg-[#f8fafc] shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                {{-- Scrollable body --}}
                <form method="POST" action="{{ route('clients.change-status', $client->id) }}" class="overflow-y-auto flex-1">
                    @csrf
                    <div class="px-7 py-5 space-y-5">

                        {{-- Current Status --}}
                        <div>
                            <p class="text-xs font-bold text-[#94a3b8] uppercase tracking-wider mb-2">Current Status</p>
                            <div class="flex items-center gap-3 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] px-4 py-3">
                                <x-ui.pill :variant="$statusTone">{{ $statusName }}</x-ui.pill>
                                @if($statusSinceFormatted)
                                    <span class="text-sm text-[#64748b]">since {{ $statusSinceFormatted }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Change To grid --}}
                        <div>
                            <p class="text-xs font-bold text-[#94a3b8] uppercase tracking-wider mb-2.5">Change To <span class="text-[#dc2626]">*</span></p>
                            <div class="grid grid-cols-2 gap-2">
                                @php
                                    $statusGrid = [
                                        ['Pending',    'indetermination'],
                                        ['Active',     ''],
                                        ['On Hold',    'indetermination'],
                                        ['Recovery',   ''],
                                        ['Discharged', ''],
                                        ['Deceased',   ''],
                                        ['Denied',     ''],
                                        [null, null],
                                    ];
                                    $dotColors = ['Pending'=>'#94a3b8','Active'=>'#16a34a','On Hold'=>'#d97706','Recovery'=>'#2563eb','Discharged'=>'#dc2626','Deceased'=>'#94a3b8','Denied'=>'#dc2626'];
                                    $statusPairs = array_chunk($statusGrid, 2);
                                @endphp
                                @foreach($statusPairs as $pair)
                                    @foreach($pair as [$sName, $sNote])
                                        @if($sName)
                                        <button type="button"
                                            @click="selected = '{{ $sName }}'"
                                            :class="selected === '{{ $sName }}' ?
                                                @php echo "'" . ($sName === 'Discharged' || $sName === 'Denied' ? 'border-[#dc2626] bg-[#fef2f2] ring-1 ring-[#dc2626]' : ($sName === 'Active' || $sName === 'Recovery' ? 'border-[#16a34a] bg-[#ecfdf3] ring-1 ring-[#16a34a]' : ($sName === 'Deceased' ? 'border-[#94a3b8] bg-[#f8fafc] ring-1 ring-[#94a3b8]' : ($sName === 'On Hold' ? 'border-[#d97706] bg-[#fff8eb] ring-1 ring-[#d97706]' : 'border-[#2563eb] bg-[#eff4ff] ring-1 ring-[#2563eb]')))) . "'" @endphp
                                                : 'border-[#e2e8f0] bg-white hover:border-[#cbd5e1] hover:bg-[#f8fafc]'"
                                            class="flex items-center gap-3 rounded-xl border px-4 py-3 text-left transition-all">
                                            <span class="w-2 h-2 rounded-full shrink-0" style="background:{{ $dotColors[$sName] ?? '#94a3b8' }}"></span>
                                            <span class="text-sm font-semibold text-[#0f172a]">{{ $sName }}</span>
                                            @if($sNote)
                                                <span class="text-xs text-[#94a3b8] ml-auto">{{ $sNote }}</span>
                                            @endif
                                        </button>
                                        @else
                                        <div></div>
                                        @endif
                                    @endforeach
                                @endforeach
                            </div>
                            <input type="hidden" name="to_status" :value="selected">
                        </div>

                        {{-- Effects box --}}
                        <div x-show="showEffects" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="rounded-xl border p-4" :class="effectsClass">
                            <div class="flex items-center gap-2 mb-2.5">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" :stroke="effectsIcon" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                <span class="text-sm font-bold" x-text="`What happens when you change to ${selected}`"></span>
                            </div>
                            <ul class="space-y-1.5 list-none pl-1">
                                <template x-for="item in (effects[selected] || [])" :key="item">
                                    <li class="flex items-start gap-2 text-sm">
                                        <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>
                                        <span x-text="item"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        {{-- Effective Date + Last Service Date --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-[#0f172a] uppercase tracking-wider mb-1.5">Effective Date <span class="text-[#dc2626]">*</span></label>
                                <div class="relative">
                                    <input type="date" name="effective_date" required
                                        value="{{ now()->toDateString() }}"
                                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm bg-white outline-none focus:border-[#2563eb] focus:ring-1 focus:ring-[#2563eb]">
                                </div>
                            </div>
                            <div x-show="showLastServiceDate" x-transition>
                                <label class="block text-xs font-bold text-[#0f172a] uppercase tracking-wider mb-1.5">Last Service Date</label>
                                <input type="date" name="last_service_date"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm bg-white outline-none focus:border-[#2563eb] focus:ring-1 focus:ring-[#2563eb]">
                            </div>
                        </div>

                        {{-- Reason --}}
                        <div x-show="selected && reasons[selected]?.length > 0" x-transition>
                            <label class="block text-xs font-bold text-[#0f172a] uppercase tracking-wider mb-1.5">Reason <span class="text-[#dc2626]">*</span></label>
                            <select name="reason" class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm bg-white outline-none focus:border-[#2563eb] focus:ring-1 focus:ring-[#2563eb]">
                                <option value="">Select a reason</option>
                                <template x-for="r in (reasons[selected] || [])" :key="r">
                                    <option :value="r" x-text="r"></option>
                                </template>
                            </select>
                            <p class="text-xs text-[#64748b] mt-1.5">Reasons adapt to the status — Discharged: Moved / Entered facility / Lost eligibility / Client request. Denied: determination denied → escalates to you. Deceased: captures date of death.</p>
                        </div>

                        {{-- Note --}}
                        <div>
                            <label class="block text-xs font-bold text-[#0f172a] uppercase tracking-wider mb-1.5">Note <span class="text-[#94a3b8] font-normal">(optional)</span></label>
                            <textarea name="note" rows="2" placeholder="Internal context, e.g. confirmed by phone…"
                                class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-1 focus:ring-[#2563eb] resize-none"></textarea>
                        </div>

                        {{-- Audit trail note --}}
                        <p class="text-xs text-[#64748b]">
                            <span class="mr-1">📋</span>Logged to the client's Audit Trail with your name, timestamp, reason and effective date.
                        </p>

                    </div>

                    {{-- Footer --}}
                    <div class="px-7 py-4 border-t border-[#eef2f9] flex items-center justify-between gap-3 shrink-0 bg-white">
                        <span class="text-sm font-semibold text-[#94a3b8]" x-show="selected">
                            Status: {{ $statusName }} →
                            <span x-text="selected" class="text-[#0f172a]"></span>
                        </span>
                        <div class="flex items-center gap-2 ml-auto">
                            <button type="button" @click="changeStatusOpen = false; selected = null"
                                class="px-4 py-2 rounded-[9px] border border-[#e2e8f0] bg-white text-sm font-semibold text-[#475569] hover:border-[#94a3b8] transition-colors">
                                Cancel
                            </button>
                            <button type="submit" :disabled="!selected"
                                :class="selected ? 'bg-[#2563eb] hover:bg-[#1d4ed8] text-white' : 'bg-[#e2e8f0] text-[#94a3b8] cursor-not-allowed'"
                                class="px-5 py-2 rounded-[9px] text-sm font-semibold transition-colors flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span x-text="actionLabel">Confirm Change</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </template>

    {{-- ── Send Request modal (wired to requests.store) ───────────────────── --}}
    @if($canSendRequest)
    <template x-teleport="body">
        <div x-show="sendRequestOpen" x-cloak class="fixed inset-0 z-[999999] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="sendRequestOpen = false"></div>
            <div class="relative w-full max-w-lg bg-white rounded-[20px] shadow-2xl overflow-hidden" @click.stop>
                <div class="px-7 py-5 border-b border-[#eef2f9] flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-bold text-[#0f172a]">Send Request</h3>
                        <p class="text-sm text-[#64748b] mt-0.5">Select a template to send for {{ $client->first_name }} {{ $client->last_name }}.</p>
                    </div>
                    <button type="button" @click="sendRequestOpen = false" class="w-8 h-8 rounded-full border border-[#eef2f9] flex items-center justify-center text-[#94a3b8] hover:bg-[#f8fafc]">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('requests.store', $client->id) }}" class="p-7 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-bold text-[#0f172a] mb-1.5">Request template</label>
                        <select name="request_template_id" required class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm bg-white outline-none focus:border-[#2563eb]">
                            <option value="">Select template</option>
                            @foreach($requestTemplates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }} ({{ ucfirst(str_replace('_', ' ', $template->recipient_type)) }})</option>
                            @endforeach
                        </select>
                        @if($requestTemplates->isEmpty())
                            <p class="text-xs text-[#b54708] mt-1.5">No active templates available. An administrator can add them under Request Templates.</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-[#0f172a] mb-1.5">Override email (optional)</label>
                        <input type="email" name="recipient_email" placeholder="For custom/other templates" class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb]">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-[#0f172a] mb-1.5">Internal notes (optional)</label>
                        <textarea name="notes" rows="3" class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb]"></textarea>
                    </div>
                    <div class="flex justify-end gap-2.5 pt-1">
                        <x-ui.btn variant="outline" type="button" x-on:click="sendRequestOpen = false">Cancel</x-ui.btn>
                        <x-ui.btn variant="primary" type="submit" x-bind:disabled="false">Send Request</x-ui.btn>
                    </div>
                </form>
            </div>
        </div>
    </template>
    @endif

    {{-- ── AI brief panel (Claude case summary) ───────────────────────────── --}}
    <x-ai.summary-panel :url="route('ai.client-summary', $client->id)" title="Daily brief — {{ $client->first_name }} {{ $client->last_name }}" />
</div>
@include('partials.google-maps-autocomplete')
@endsection

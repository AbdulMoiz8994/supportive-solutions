@extends('layouts.app')

@section('content')
@php
    $rowsJson = $rows;
@endphp

<div class="space-y-6 pb-20 w-full px-2"
     x-data="caregiverRegistry(@js($rowsJson), @js($kpis))">

    {{-- Dashboard-style top sub-nav --}}
    <div class="flex flex-wrap items-center gap-2 pt-2">
        @php $topTabs = [
            ['Live Dashboard', route('caregivers'), true],
            ['Visit Reports', route('visit-reports'), false],
            ['Forms', route('dashboard.forms'), false],
            ['Client Intake', route('intakes.index'), false],
            ['Data Exploration 2.0', route('data-exploration'), false],
            ['Tasks', route('tasks'), false],
        ]; @endphp
        @foreach($topTabs as [$label, $url, $active])
            <a href="{{ $url }}" class="px-4 py-2 rounded-xl text-[12px] font-bold transition-all {{ $active ? 'bg-[#2563eb] text-white shadow-lg shadow-blue-100' : 'bg-white text-[#475569] border border-[#e2e8f0] hover:bg-gray-50' }}">{{ $label }}</a>
        @endforeach
    </div>

    {{-- Page header --}}
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <h1 class="text-[28px] font-black text-[#1e293b] tracking-tight">Caregiver</h1>
            <p class="text-[12px] font-medium text-[#64748b] mt-1" x-text="statsLine">{{ $kpis['total'] }} total · {{ $kpis['active'] }} active · {{ $kpis['pending'] }} pending onboarding · {{ $kpis['on_leave'] }} on leave · {{ $kpis['on_hold'] }} on hold · {{ $kpis['inactive'] ?? 0 }} inactive · ~{{ $kpis['family_pct'] }}% family caregivers</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('caregivers.export') }}" class="px-5 py-2.5 bg-white border border-[#e2e8f0] text-[#475569] rounded-xl text-[12px] font-bold shadow-sm hover:bg-gray-50 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export
            </a>
            <a href="{{ route('caregivers.create') }}" class="px-5 py-2.5 bg-[#2563eb] text-white rounded-xl text-[12px] font-bold shadow-lg shadow-blue-100 hover:bg-[#1d4ed8] flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14"/></svg>
                New Caregiver / Onboard
            </a>
        </div>
    </div>

    {{-- KPI strip --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @php
            $kpiCards = [
                ['Active caregivers', $kpis['active'], 'Ready for assignments', 'blue', 'all'],
                ['Background check expiring ≤30d', $kpis['checks_expiring'], 'ICHAT annual renewals', 'amber', 'expiring'],
                ['Background check flagged', $kpis['checks_flagged'], 'Verify same-person', 'red', 'flagged'],
                ['On hold', $kpis['on_hold'], 'Service paused', 'orange', 'hold'],
                ['Compliance missing ('.now()->format('M').')', $kpis['compliance_missing'], 'Form not yet received', 'violet', 'compliance'],
                ['Pending onboarding', $kpis['pending'], 'In your queue', 'violet', 'pending'],
            ];
            $tone = [
                'blue' => 'text-[#2563eb] bg-blue-100/50', 'amber' => 'text-amber-600 bg-amber-100/50',
                'red' => 'text-red-600 bg-red-100/50', 'orange' => 'text-orange-600 bg-orange-100/50',
                'violet' => 'text-violet-600 bg-violet-100/50',
            ];
        @endphp
        @foreach($kpiCards as [$label, $value, $sub, $color, $filter])
            <button @click="applyKpi('{{ $filter }}')" class="text-left bg-[#eff6ff] p-4 rounded-[20px] border border-blue-100/50 shadow-sm hover:border-blue-300 transition-all">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center mb-3 {{ $tone[$color] }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <p class="text-[10px] font-bold text-[#64748b] uppercase tracking-wider leading-tight">{{ $label }}</p>
                <p class="text-[26px] font-black text-[#1e293b] leading-none mt-1">{{ $value }}</p>
                <p class="text-[10px] font-medium text-[#94a3b8] mt-1">{{ $sub }}</p>
            </button>
        @endforeach
    </div>

    {{-- Filter chips --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative min-w-[260px]">
            <svg class="w-4 h-4 text-[#94a3b8] absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="search" @input="page=1" placeholder="Filter by name, SSN, client, phone…"
                class="w-full pl-10 pr-4 py-2 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-semibold text-[#1e293b] placeholder:text-[#94a3b8] outline-none focus:ring-2 focus:ring-blue-500/10">
        </div>
        @php $chips = [
            ['Type: All', 'all'], ['Family', 'family'], ['Agency-sourced', 'agency'],
            ['Program: DHS', 'dhs'], ['Program: MICH', 'mich'],
            ['Checks expiring ≤30d', 'expiring'], ['Live-in', 'livein'], ['Compliance missing', 'compliance'],
        ]; @endphp
        @foreach($chips as [$label, $key])
            <button @click="toggleChip('{{ $key }}')"
                :class="filter === '{{ $key }}' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0] hover:bg-gray-50'"
                class="px-3.5 py-2 rounded-full border text-[11px] font-bold transition-all">{{ $label }}</button>
        @endforeach
    </div>

    {{-- Secondary tab nav (visual parity) --}}
    <div class="border-b border-blue-100/40 -mb-2">
        <nav class="flex gap-7 overflow-x-auto no-scrollbar">
            @foreach(['All '.$kpis['total'] => true, 'Client Info'=>false, 'Status'=>false, 'Documents'=>false, 'Notes / Activity'=>false, 'Schedule'=>false, 'Time & Tasks'=>false, 'Billing'=>false] as $tab => $on)
                <span class="py-3 text-[12px] font-bold whitespace-nowrap border-b-2 {{ $on ? 'border-[#2563eb] text-[#1e293b]' : 'border-transparent text-[#94a3b8]' }}">{{ $tab }}</span>
            @endforeach
        </nav>
    </div>

    {{-- Main table --}}
    <div class="bg-[#eff6ff] rounded-[24px] border border-blue-100/50 overflow-hidden shadow-sm">
        <div class="px-6 py-4 flex items-center justify-between border-b border-blue-100/20">
            <h2 class="text-[15px] font-bold text-[#1e293b]">All Caregivers <span class="text-[#94a3b8] font-semibold" x-text="'(' + filtered.length + (filter === 'all' && !search ? ' of {{ $kpis['total'] }}' : '') + ')'"></span></h2>
        </div>
        <div class="w-full overflow-x-auto no-scrollbar">
            <table class="w-full min-w-[1080px] border-collapse">
                <thead>
                    <tr class="bg-white border-b border-blue-100/20 text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">
                        <th class="px-6 py-3.5 text-left">Name</th>
                        <th class="px-4 py-3.5 text-left">SSN</th>
                        <th class="px-4 py-3.5 text-left">DOB (Age)</th>
                        <th class="px-4 py-3.5 text-left">Client(s) Served</th>
                        <th class="px-4 py-3.5 text-left">Program</th>
                        <th class="px-4 py-3.5 text-left">Background Checks</th>
                        <th class="px-4 py-3.5 text-left">EVV</th>
                        <th class="px-4 py-3.5 text-left">Last Compliance</th>
                        <th class="px-4 py-3.5 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-blue-100/10">
                    <template x-for="r in paginated" :key="r.id">
                        <tr class="hover:bg-blue-50/40 transition-colors cursor-pointer" @click="window.location='/caregivers/' + r.id">
                            <td class="px-6 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <img :src="'https://ui-avatars.com/api/?name=' + encodeURIComponent(r.name) + '&background=dbeafe&color=1e3a8a&bold=true'" class="w-7 h-7 rounded-lg">
                                    <span class="text-[12.5px] font-bold text-[#1e293b]" x-text="r.name"></span>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-[12px] font-medium text-[#475569]" x-text="r.ssn"></td>
                            <td class="px-4 py-3.5 text-[12px] font-medium text-[#475569]" x-text="r.dob"></td>
                            <td class="px-4 py-3.5 text-[12px] font-medium text-[#475569]" x-text="r.served"></td>
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-orange-50 text-orange-600" x-text="r.program"></span>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="text-[12px] font-semibold"
                                    :class="{'text-green-600': r.checkTone==='clear','text-amber-600': r.checkTone==='due','text-blue-600': r.checkTone==='progress','text-red-600': r.checkTone==='flag'}"
                                    x-text="r.checkLabel"></span>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold"
                                    :class="r.liveIn ? 'bg-orange-50 text-orange-600' : 'bg-blue-50 text-blue-600'" x-text="r.evv"></span>
                            </td>
                            <td class="px-4 py-3.5 text-[12px] font-medium text-[#475569]" x-text="r.lastComp"></td>
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold"
                                    :class="{'bg-blue-50 text-blue-600': r.status==='Active','bg-orange-50 text-orange-600': r.status==='Pending','bg-red-50 text-red-600': r.status==='On Hold','bg-amber-50 text-amber-700': r.status==='On Leave','bg-gray-100 text-gray-600': r.status==='Inactive'}"
                                    x-text="r.status"></span>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filtered.length === 0">
                        <td colspan="9" class="px-6 py-12 text-center text-[#94a3b8] font-bold italic">No caregivers match your filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-blue-100/20">
            <p class="text-[12px] font-bold text-[#94a3b8]">
                Showing <span class="text-[#1e293b]" x-text="filtered.length ? ((page-1)*perPage+1) : 0"></span>–<span class="text-[#1e293b]" x-text="Math.min(page*perPage, filtered.length)"></span> of <span class="text-[#1e293b]" x-text="filtered.length"></span>
            </p>
            <div class="flex items-center gap-2">
                <button @click="page>1 && page--" :disabled="page===1" class="px-3 py-1.5 rounded-lg border border-[#e2e8f0] bg-white text-[12px] font-bold disabled:opacity-40">Prev</button>
                <span class="text-[12px] font-bold text-[#475569]" x-text="page + ' / ' + totalPages"></span>
                <button @click="page<totalPages && page++" :disabled="page===totalPages" class="px-3 py-1.5 rounded-lg border border-[#e2e8f0] bg-white text-[12px] font-bold disabled:opacity-40">Next</button>
            </div>
        </div>
    </div>

    </div>

<script>
function caregiverRegistry(rows, kpis) {
    return {
        rows: rows,
        kpis: kpis || {},
        search: '',
        filter: 'all',
        page: 1,
        perPage: 10,
        toggleChip(key) { this.filter = (this.filter === key) ? 'all' : key; this.page = 1; },
        applyKpi(key) {
            const map = { expiring: 'expiring', hold: 'hold', flagged: 'flagged', compliance: 'compliance', pending: 'pending', all: 'all' };
            this.filter = map[key] || 'all'; this.page = 1;
        },
        get filtered() {
            const s = this.search.toLowerCase();
            return this.rows.filter(r => {
                const matchesSearch = !s || (r.name + ' ' + r.ssn + ' ' + r.served).toLowerCase().includes(s);
                let matchesFilter = true;
                switch (this.filter) {
                    case 'family':     matchesFilter = r.type === 'Family'; break;
                    case 'agency':     matchesFilter = r.type === 'Agency'; break;
                    case 'dhs':        matchesFilter = (r.program || '').includes('DHS'); break;
                    case 'mich':       matchesFilter = (r.program || '').includes('MICH'); break;
                    case 'expiring':   matchesFilter = !!r.checks_expiring; break;
                    case 'flagged':    matchesFilter = !!r.checks_flagged; break;
                    case 'livein':     matchesFilter = !!r.liveIn; break;
                    case 'compliance': matchesFilter = !!r.compliance_missing; break;
                    case 'hold':       matchesFilter = r.status === 'On Hold'; break;
                    case 'pending':    matchesFilter = r.status === 'Pending'; break;
                }
                return matchesSearch && matchesFilter;
            });
        },
        get visibleStats() {
            const list = this.filtered;
            const total = list.length;
            const count = (fn) => list.filter(fn).length;
            const familyCount = count(r => r.type === 'Family');

            return {
                total,
                active: count(r => r.status === 'Active'),
                pending: count(r => r.status === 'Pending'),
                onHold: count(r => r.status === 'On Hold'),
                onLeave: count(r => r.status === 'On Leave'),
                inactive: count(r => r.status === 'Inactive'),
                familyPct: total ? Math.round((100 * familyCount) / total) : 0,
            };
        },
        get statsLine() {
            const s = this.visibleStats;
            const scoped = this.search || this.filter !== 'all';
            const totalLabel = scoped ? s.total + ' matching' : s.total + ' total';

            return `${totalLabel} · ${s.active} active · ${s.pending} pending onboarding · ${s.onLeave} on leave · ${s.onHold} on hold · ${s.inactive} inactive · ~${s.familyPct}% family caregivers`;
        },
        get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
        get paginated() {
            const start = (this.page - 1) * this.perPage;
            return this.filtered.slice(start, start + this.perPage);
        },
    };
}
</script>
@endsection

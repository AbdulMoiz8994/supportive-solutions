@extends('layouts.app')

@section('content')
<div class="space-y-6 pb-20 w-full px-2" x-data="bgRegistry(@js($rows), @js($kpis))">

    {{-- Page header --}}
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 pt-2">
        <div>
            <h1 class="text-[28px] font-black text-[#1e293b] tracking-tight">Background Checks</h1>
            <p class="text-[12px] font-medium text-[#64748b] mt-1">
                {{ $kpis['monitored'] }} caregivers monitored · {{ $kpis['ichat_due'] }} ICHAT renewals due · {{ $kpis['flags'] }} flag{{ $kpis['flags'] == 1 ? '' : 's' }} to verify
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-5 py-2.5 bg-white border border-[#e2e8f0] text-[#475569] rounded-xl text-[12px] font-bold shadow-sm flex items-center gap-2 cursor-default">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export
            </span>
            <span class="px-5 py-2.5 bg-[#2563eb] text-white rounded-xl text-[12px] font-bold shadow-lg shadow-blue-100 flex items-center gap-2 cursor-default" title="Runs the free SAM.gov + OIG LEIE batch on demand">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Run monthly batch
            </span>
        </div>
    </div>

    {{-- KPI strip --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">
        @php
            $bgKpis = [
                ['Caregivers monitored', $kpis['monitored'], 'all 4 checks tracked', 'green', 'all'],
                ['ICHAT due ≤ 30 days', $kpis['ichat_due'], 'annual renewals', 'amber', 'ichat'],
                ['Flags to verify', $kpis['flags'], 'in your approval queue', 'red', 'flagged'],
                ['Monthly SAM/OIG batch', $kpis['batch_clear'].'/'.$kpis['monitored'], 'free monthly · clear', 'green', 'all'],
                ['In onboarding', $kpis['onboarding'], 'CHAMPS/ICHAT pending', 'amber', 'onboarding'],
            ];
            $tone = ['green' => 'text-emerald-600', 'amber' => 'text-amber-600', 'red' => 'text-red-600'];
        @endphp
        @foreach($bgKpis as [$label, $value, $sub, $color, $filter])
            <button @click="applyKpi('{{ $filter }}')" class="text-left bg-[#eff6ff] p-4 rounded-[20px] border border-blue-100/50 shadow-sm hover:border-blue-300 transition-all">
                <p class="text-[10px] font-bold text-[#64748b] uppercase tracking-wider leading-tight">{{ $label }}</p>
                <p class="text-[26px] font-black {{ $tone[$color] }} leading-none mt-1.5">{{ $value }}</p>
                <p class="text-[10px] font-medium text-[#94a3b8] mt-1">{{ $sub }}</p>
            </button>
        @endforeach
    </div>

    {{-- Monthly batch banner --}}
    <div class="flex items-center gap-4 rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-emerald-100/60 px-5 py-3.5">
        <span class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <div class="flex-1">
            <p class="text-[13.5px] font-bold text-emerald-900">Monthly SAM.gov + OIG LEIE batch — {{ $kpis['batch_clear'] }} of {{ $kpis['monitored'] }} clear</p>
            <p class="text-[12px] text-emerald-700 mt-0.5">Free monthly runs (SAM API + OIG download). CHAMPS monitors continuously; ICHAT renews annually per caregiver.</p>
        </div>
        <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700">{{ $kpis['flags'] === 0 ? 'All clear' : $kpis['flags'].' to verify' }}</span>
    </div>

    {{-- Filter chips --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative min-w-[260px]">
            <svg class="w-4 h-4 text-[#94a3b8] absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="search" @input="page=1" placeholder="Filter by caregiver…"
                class="w-full pl-10 pr-4 py-2 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-semibold text-[#1e293b] placeholder:text-[#94a3b8] outline-none focus:ring-2 focus:ring-blue-500/10">
        </div>
        @php $chips = [
            ['All checks', 'all'], ['All clear', 'clear'], ['ICHAT due ≤30d', 'ichat'],
            ['Flagged', 'flagged'], ['In onboarding', 'onboarding'],
        ]; @endphp
        @foreach($chips as [$label, $key])
            <button @click="toggleChip('{{ $key }}')"
                :class="filter === '{{ $key }}' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0] hover:bg-gray-50'"
                class="px-3.5 py-2 rounded-full border text-[11px] font-bold transition-all">{{ $label }}</button>
        @endforeach
    </div>

    {{-- Matrix table --}}
    <div class="bg-[#eff6ff] rounded-[24px] border border-blue-100/50 overflow-hidden shadow-sm">
        <div class="px-6 py-4 flex items-center justify-between border-b border-blue-100/20">
            <h2 class="text-[15px] font-bold text-[#1e293b]">All Caregivers <span class="text-[#94a3b8] font-semibold" x-text="'(' + filtered.length + ')'"></span></h2>
        </div>
        <div class="w-full overflow-x-auto no-scrollbar">
            <table class="w-full min-w-[920px] border-collapse">
                <thead>
                    <tr class="bg-white border-b border-blue-100/20 text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">
                        <th class="px-6 py-3.5 text-left">Caregiver</th>
                        <th class="px-4 py-3.5 text-left">CHAMPS</th>
                        <th class="px-4 py-3.5 text-left">ICHAT</th>
                        <th class="px-4 py-3.5 text-left">SAM.gov</th>
                        <th class="px-4 py-3.5 text-left">OIG LEIE</th>
                        <th class="px-4 py-3.5 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-blue-100/10">
                    <template x-for="r in paginated" :key="r.id">
                        <tr class="hover:bg-blue-50/40 transition-colors cursor-pointer" @click="window.location='/caregivers/' + r.id + '?tab=checks'">
                            <td class="px-6 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-7 h-7 rounded-lg bg-gradient-to-br from-sky-400 to-sky-700 text-white text-[10px] font-bold flex items-center justify-center" x-text="r.initials"></span>
                                    <span class="text-[12.5px] font-bold text-[#1e293b]" x-text="r.name"></span>
                                </div>
                            </td>
                            <template x-for="cell in [['champs_label','champs_tone',null],['ichat_label','ichat_tone','ichat_sub'],['sam_label','sam_tone','sam_sub'],['oig_label','oig_tone','oig_sub']]" :key="cell[0]">
                                <td class="px-4 py-3.5 text-[12px] text-[#475569] whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full" :class="{'bg-emerald-500': r[cell[1]]==='g','bg-amber-500': r[cell[1]]==='a','bg-red-500': r[cell[1]]==='r','bg-slate-300': r[cell[1]]==='x'}"></span>
                                        <span class="font-semibold" x-text="r[cell[0]]"></span>
                                    </span>
                                    <span class="block text-[10.5px] text-[#94a3b8] ml-3.5" x-show="cell[2] && r[cell[2]]" x-text="cell[2] ? r[cell[2]] : ''"></span>
                                </td>
                            </template>
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold"
                                    :class="{'bg-emerald-50 text-emerald-700': r.status_tone==='green','bg-amber-50 text-amber-700': r.status_tone==='amber','bg-red-50 text-red-700': r.status_tone==='red'}"
                                    x-text="r.status_label"></span>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filtered.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center text-[#94a3b8] font-bold italic">No caregivers match your filters.</td>
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

    {{-- What this page does --}}
    <div class="rounded-2xl border border-dashed border-[#cbd5e1] bg-white p-5">
        <p class="text-[11px] font-black text-[#2563eb] uppercase tracking-wider mb-2">What this page does</p>
        <ul class="list-disc pl-5 space-y-1 text-[12.5px] text-[#475569]">
            <li>One matrix for all four checks — CHAMPS, ICHAT, SAM, OIG — across every caregiver, with an overall column. Each row opens that caregiver's Background Checks tab.</li>
            <li>Cadence-aware: SAM/OIG run monthly (free), ICHAT renews annually (countdown shown), CHAMPS monitors continuously.</li>
            <li>Flags route to <b>verify same-person</b> (On Hold) — never auto-disqualify. A flagged or onboarding caregiver can't be assigned or paid.</li>
            <li class="text-[#94a3b8]"><b>Coming online with accounts:</b> SAM + OIG are free/public; auto-running <b>ICHAT</b> needs the agency's Michigan State Police account, and CHAMPS is portal-driven (RPA login).</li>
        </ul>
    </div>
</div>

@push('scripts')
<script>
function bgRegistry(rows, kpis) {
    return {
        rows: rows || [], kpis: kpis || {}, search: '', filter: 'all', page: 1, perPage: 10,
        toggleChip(key) { this.filter = (this.filter === key) ? 'all' : key; this.page = 1; },
        applyKpi(key) { this.filter = key || 'all'; this.page = 1; },
        get filtered() {
            const s = this.search.toLowerCase();
            return this.rows.filter(r => {
                const matchesSearch = !s || r.name.toLowerCase().includes(s);
                let ok = true;
                switch (this.filter) {
                    case 'clear':      ok = r.status_key === 'clear'; break;
                    case 'ichat':      ok = !!r.ichat_due; break;
                    case 'flagged':    ok = r.status_key === 'flagged'; break;
                    case 'onboarding': ok = r.status_key === 'onboarding'; break;
                }
                return matchesSearch && ok;
            });
        },
        get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
        get paginated() { const start = (this.page - 1) * this.perPage; return this.filtered.slice(start, start + this.perPage); },
    };
}
</script>
@endpush
@endsection

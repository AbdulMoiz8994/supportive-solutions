@extends('layouts.app')

@section('content')
<div class="space-y-6 pb-20 w-full px-2" x-data="authRegistry(@js($rows), @js($kpis))">

    {{-- Page header --}}
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 pt-2">
        <div>
            <h1 class="text-[28px] font-black text-[#1e293b] tracking-tight">Authorizations</h1>
            <p class="text-[12px] font-medium text-[#64748b] mt-1">
                {{ $kpis['active'] }} active · {{ $kpis['expiring_21'] }} expiring ≤21 days · {{ $kpis['expired_hold'] }} expired/on-hold · {{ $kpis['dhs_reassess'] }} DHS reassessments due
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-5 py-2.5 bg-white border border-[#e2e8f0] text-[#475569] rounded-xl text-[12px] font-bold shadow-sm flex items-center gap-2 cursor-default">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export
            </span>
            <button type="button" x-on:click="logAuthOpen = true" class="px-5 py-2.5 bg-[#2563eb] text-white rounded-xl text-[12px] font-bold shadow-lg shadow-blue-100 hover:bg-[#1d4ed8] flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14"/></svg>
                Log authorization
            </button>
        </div>
    </div>

    {{-- Log authorization: pick a client, then open that client's add-authorization form --}}
    <template x-teleport="body">
        <div x-show="logAuthOpen" x-cloak class="fixed inset-0 z-[999999] flex items-center justify-center p-4"
             @keydown.escape.window="logAuthOpen = false">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="logAuthOpen = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" @click.stop>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-base font-bold text-[#0f172a]">Log authorization</h3>
                    <button type="button" @click="logAuthOpen = false" class="text-[#94a3b8] hover:text-[#475569] text-xl leading-none">&times;</button>
                </div>
                <p class="text-xs text-[#64748b] mb-4">Authorizations belong to a client. Pick the client and we'll open their add-authorization form.</p>
                <label class="block text-xs font-bold text-[#94a3b8] uppercase tracking-wide mb-1.5">Client <span class="text-[#ef4444]">*</span></label>
                <div class="relative">
                    <select x-model="logAuthClient"
                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-medium text-[#0f172a] bg-white appearance-none pr-9 outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                        <option value="">Select a client…</option>
                        @foreach($clientOptions as $opt)
                            <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                        @endforeach
                    </select>
                    <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="flex items-center justify-end gap-3 pt-5">
                    <button type="button" @click="logAuthOpen = false"
                        class="px-4 py-2 text-sm font-semibold text-[#475569] border border-[#e2e8f0] rounded-[9px] hover:bg-gray-50 transition">Cancel</button>
                    <button type="button" :disabled="!logAuthClient"
                        :class="logAuthClient ? 'bg-[#2563eb] hover:bg-[#1d4ed8]' : 'bg-[#93b4f5] cursor-not-allowed'"
                        @click="if (logAuthClient) window.location.href = '{{ url('clients') }}/' + logAuthClient + '?tab=authorization&add_auth=1'"
                        class="px-5 py-2 text-sm font-semibold text-white border border-transparent rounded-[9px] transition shadow-sm">Continue →</button>
                </div>
            </div>
        </div>
    </template>

    {{-- KPI strip --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">
        @php
            $authKpis = [
                ['Active authorizations', $kpis['active'], $kpis['dhs'].' DHS · '.$kpis['mich'].' MICH', 'blue', 'all'],
                ['PA expiring ≤ 21 days', $kpis['expiring_21'], 'MICH — renew now', 'amber', 'expiring'],
                ['Expired / on hold', $kpis['expired_hold'], 'Service stopped', 'red', 'expired'],
                ['Renewals in progress', $kpis['renewals'], 'In approval queue', 'amber', 'renewal'],
                ['DHS reassessments ≤ 60d', $kpis['dhs_reassess'], '6-month review', 'violet', 'reassess'],
            ];
            $tone = [
                'blue' => 'text-[#2563eb]', 'amber' => 'text-amber-600',
                'red' => 'text-red-600', 'violet' => 'text-violet-600',
            ];
        @endphp
        @foreach($authKpis as [$label, $value, $sub, $color, $filter])
            <button @click="applyKpi('{{ $filter }}')" class="text-left bg-[#eff6ff] p-4 rounded-[20px] border border-blue-100/50 shadow-sm hover:border-blue-300 transition-all">
                <p class="text-[10px] font-bold text-[#64748b] uppercase tracking-wider leading-tight">{{ $label }}</p>
                <p class="text-[26px] font-black {{ $tone[$color] }} leading-none mt-1.5">{{ $value }}</p>
                <p class="text-[10px] font-medium text-[#94a3b8] mt-1">{{ $sub }}</p>
            </button>
        @endforeach
    </div>

    {{-- Filter chips --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative min-w-[260px]">
            <svg class="w-4 h-4 text-[#94a3b8] absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="search" @input="page=1" placeholder="Filter by client, auth #, MCO…"
                class="w-full pl-10 pr-4 py-2 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-semibold text-[#1e293b] placeholder:text-[#94a3b8] outline-none focus:ring-2 focus:ring-blue-500/10">
        </div>
        @php $chips = [
            ['Status: All', 'all'], ['MICH', 'mich'], ['DHS', 'dhs'],
            ['Expiring ≤21d', 'expiring'], ['Expired / on hold', 'expired'],
            ['Renewals in progress', 'renewal'], ['Reassessment due', 'reassess'],
        ]; @endphp
        @foreach($chips as [$label, $key])
            <button @click="toggleChip('{{ $key }}')"
                :class="filter === '{{ $key }}' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0] hover:bg-gray-50'"
                class="px-3.5 py-2 rounded-full border text-[11px] font-bold transition-all">{{ $label }}</button>
        @endforeach
        <span class="ml-auto text-[11px] font-semibold text-[#94a3b8]">Sorted soonest-first</span>
    </div>

    {{-- Main table --}}
    <div class="bg-[#eff6ff] rounded-[24px] border border-blue-100/50 overflow-hidden shadow-sm">
        <div class="px-6 py-4 flex items-center justify-between border-b border-blue-100/20">
            <h2 class="text-[15px] font-bold text-[#1e293b]">All Authorizations <span class="text-[#94a3b8] font-semibold" x-text="'(' + filtered.length + ')'"></span></h2>
        </div>
        <div class="w-full overflow-x-auto no-scrollbar">
            <table class="w-full min-w-[1020px] border-collapse">
                <thead>
                    <tr class="bg-white border-b border-blue-100/20 text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">
                        <th class="px-6 py-3.5 text-left">Client</th>
                        <th class="px-4 py-3.5 text-left">Program</th>
                        <th class="px-4 py-3.5 text-left">Type</th>
                        <th class="px-4 py-3.5 text-left">MCO / ASW</th>
                        <th class="px-4 py-3.5 text-left">Auth ref</th>
                        <th class="px-4 py-3.5 text-left">Units / Hrs</th>
                        <th class="px-4 py-3.5 text-left">Expires / Reassess</th>
                        <th class="px-4 py-3.5 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-blue-100/10">
                    <template x-for="r in paginated" :key="r.id">
                        <tr class="hover:bg-blue-50/40 transition-colors cursor-pointer" @click="window.location='/clients/' + r.client_id + '?tab=authorization'">
                            <td class="px-6 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-7 h-7 rounded-lg bg-gradient-to-br from-slate-400 to-slate-600 text-white text-[10px] font-bold flex items-center justify-center" x-text="r.initials"></span>
                                    <span class="text-[12.5px] font-bold text-[#1e293b]" x-text="r.name"></span>
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold" :class="r.program === 'DHS' ? 'bg-slate-100 text-slate-600' : 'bg-blue-50 text-blue-700'" x-text="r.program_display || r.program"></span>
                            </td>
                            <td class="px-4 py-3.5 text-[12px] font-medium text-[#475569]" x-text="r.type"></td>
                            <td class="px-4 py-3.5 text-[12px] font-medium text-[#475569]" x-text="r.mco"></td>
                            <td class="px-4 py-3.5 text-[12px] font-medium text-[#475569]" x-text="r.auth_ref"></td>
                            <td class="px-4 py-3.5 text-[12px] font-medium text-[#475569]" x-text="r.units"></td>
                            <td class="px-4 py-3.5 text-[12px] font-bold">
                                <span :class="{'text-red-600': r.expires_tone==='red','text-amber-600': r.expires_tone==='amber','text-emerald-600': r.expires_tone==='green','text-slate-400': r.expires_tone==='grey'}" x-text="r.expires"></span>
                                <span class="text-[#94a3b8] font-medium" x-show="r.expires_sub" x-text="' ' + r.expires_sub"></span>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold"
                                    :class="{'bg-emerald-50 text-emerald-700': r.status_tone==='green','bg-amber-50 text-amber-700': r.status_tone==='amber','bg-red-50 text-red-700': r.status_tone==='red','bg-violet-50 text-violet-700': r.status_tone==='violet'}"
                                    x-text="r.status_label"></span>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filtered.length === 0">
                        <td colspan="8" class="px-6 py-12 text-center text-[#94a3b8] font-bold italic">No authorizations match your filters.</td>
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
            <li>One agency-wide list of every authorization — MICH Prior Auths and DHS Time/Task Sheets — sorted soonest-first so at-risk ones surface first.</li>
            <li>Program-aware: PAs show an expiry countdown; DHS Time/Task show "reassess [date] · no expiry" (the 6-month review) — never a false "expired".</li>
            <li>The hard rule is visible: an expired PA reads <b>On Hold</b> (service stops on expiry). Each row opens the client's Program &amp; Authorization tab.</li>
            <li class="text-[#94a3b8]"><b>Coming online with EVV:</b> "units used / remaining" fills in once HHAeXchange clock-in data is connected.</li>
        </ul>
    </div>
</div>

@push('scripts')
<script>
function authRegistry(rows, kpis) {
    return {
        rows: rows || [], kpis: kpis || {}, search: '', filter: 'all', page: 1, perPage: 10,
        logAuthOpen: false, logAuthClient: '',
        toggleChip(key) { this.filter = (this.filter === key) ? 'all' : key; this.page = 1; },
        applyKpi(key) { this.filter = key || 'all'; this.page = 1; },
        get filtered() {
            const s = this.search.toLowerCase();
            return this.rows.filter(r => {
                const hay = (r.name + ' ' + r.auth_ref + ' ' + r.mco + ' ' + r.program).toLowerCase();
                const matchesSearch = !s || hay.includes(s);
                let ok = true;
                switch (this.filter) {
                    case 'mich':      ok = r.program === 'MICH'; break;
                    case 'dhs':       ok = r.program === 'DHS'; break;
                    case 'expiring':  ok = !!r.expiring_21; break;
                    case 'expired':   ok = r.status_key === 'expired'; break;
                    case 'renewal':   ok = r.status_key === 'renewal'; break;
                    case 'reassess':  ok = !!r.reassess_60; break;
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

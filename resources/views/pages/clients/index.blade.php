@extends('layouts.app')

@section('content')
<div class="max-w-full mx-auto pb-12"
     x-data="{
        rows: @js($rows),
        tabCounts: @js($tabCounts),
        search: '',
        tab: 'all',
        program: 'all',
        page: 1,
        perPage: 10,

        pillClass(tone) {
            return ({
                gray:   'bg-[#f1f5f9] text-[#475569] border-[#e2e8f0]',
                blue:   'bg-[#eff4ff] text-[#2563eb] border-[#dbe6ff]',
                green:  'bg-[#ecfdf3] text-[#067647] border-[#d1fadf]',
                amber:  'bg-[#fff8eb] text-[#b54708] border-[#fdecc8]',
                red:    'bg-[#fef3f2] text-[#d92d20] border-[#fee4e2]',
                indigo: 'bg-[#eef2ff] text-[#4338ca] border-[#e0e7ff]',
            })[tone] || 'bg-[#f1f5f9] text-[#475569] border-[#e2e8f0]';
        },
        matchFilters(r, tab) {
            const q = this.search.toLowerCase();
            const hay = [r.name, r.medicaid, r.county, r.caregiver, r.mco].filter(Boolean).join(' ').toLowerCase();
            if (q && !hay.includes(q)) return false;
            if (this.program !== 'all' && r.program !== this.program) return false;
            const statusKey = r.status_key || '';
            switch (tab) {
                case 'active':       return statusKey === 'active';
                case 'pending_dhs':  return statusKey === 'pending' && r.program === 'DHS';
                case 'pending_mich': return statusKey === 'pending' && r.program === 'MICH';
                case 'recovery':     return statusKey === 'recovery';
                case 'on_hold':      return statusKey === 'on_hold';
                case 'discharged':   return statusKey === 'discharged';
                default:             return true;
            }
        },
        get filtered() { return this.rows.filter(r => this.matchFilters(r, this.tab)); },
        countTab(t) { return this.tabCounts[t] ?? this.rows.filter(r => this.matchFilters(r, t)).length; },
        get total() { return this.filtered.length; },
        get totalPages() { return Math.max(1, Math.ceil(this.total / this.perPage)); },
        get paged() {
            const start = (this.page - 1) * this.perPage;
            return this.filtered.slice(start, start + this.perPage);
        },
        get startEntry() { return this.total === 0 ? 0 : (this.page - 1) * this.perPage + 1; },
        get endEntry() { return Math.min(this.page * this.perPage, this.total); },
     }"
     x-init="$watch('search', () => page = 1); $watch('tab', () => page = 1); $watch('program', () => page = 1)">

    {{-- Flash --}}
    @if(session('success'))
        <div x-data="{show:true}" x-show="show" x-transition class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-[#d1fadf] bg-[#ecfdf3] px-4 py-3 text-sm font-semibold text-[#067647]">
            <span>{{ session('success') }}</span>
            <button @click="show=false" class="text-[#067647]/60 hover:text-[#067647]">&times;</button>
        </div>
    @endif

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
        <div>
            <h1 class="text-2xl font-extrabold text-[#0f172a] tracking-tight leading-tight">Clients</h1>
            <p class="text-sm text-[#64748b] mt-1">
                {{ number_format($stats['active']) }} active
                @if($stats['on_hold']) · {{ $stats['on_hold'] }} on hold @endif
                @if($stats['auth_expired']) · {{ $stats['auth_expired'] }} auth expired @endif
                @if($stats['discharged']) · {{ $stats['discharged'] }} discharged @endif
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <x-ui.btn variant="outline" :href="route('clients.export')" icon='<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>'>Export</x-ui.btn>
            <a href="{{ route('intakes.index') }}"
                class="inline-flex items-center gap-1.5 font-semibold rounded-[10px] text-sm px-4 py-2.5 bg-white border border-[#d8e2f0] text-[#334155] hover:border-[#94a3b8] transition-colors shadow-sm">
                New intake
            </a>
            <a href="{{ route('clients.create') }}"
                class="inline-flex items-center gap-1.5 font-semibold rounded-[10px] text-sm px-4 py-2.5 bg-[#2563eb] text-white hover:bg-[#1d4ed8] transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Enrol client
            </a>
        </div>
    </div>

    {{-- ── KPI stat cards ─────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 mb-5">
        <x-ui.stat-card label="Active clients" :value="number_format($stats['active'])"
            :sub="$stats['dhs'].' active DHS · '.$stats['mich'].' active MICH'" />
        <x-ui.stat-card label="Auth expiring ≤ 21 days" :value="$stats['auth_expiring']"
            sub="MICH PAs — renew soon" />
        <x-ui.stat-card label="Auth expired" :value="$stats['auth_expired']"
            sub="Service at risk" />
        <x-ui.stat-card label="On hold" :value="$stats['on_hold']"
            sub="Service paused" />
        <x-ui.stat-card label="Total clients" :value="number_format($stats['total'])"
            sub="In registry" />
    </div>

    {{-- ── Status filter tabs ─────────────────────────────────────────────── --}}
    <div class="flex items-center gap-1 overflow-x-auto no-scrollbar border-b border-[#e6eef9] mb-4">
        @php
            $tabs = [
                'all' => 'All', 'active' => 'Approved active', 'pending_dhs' => 'Pending DHS',
                'pending_mich' => 'Pending MICH', 'recovery' => 'Recovery', 'on_hold' => 'On hold',
                'discharged' => 'Discharged',
            ];
        @endphp
        @foreach($tabs as $key => $label)
            <button x-on:click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'text-[#0f172a] border-[#2563eb]' : 'text-[#94a3b8] border-transparent hover:text-[#475569]'"
                class="relative whitespace-nowrap px-3.5 py-2.5 text-sm font-semibold border-b-2 transition-colors -mb-px">
                {{ $label }}
                <span class="ml-1.5 text-xs font-bold text-[#94a3b8]" x-text="countTab('{{ $key }}')"></span>
            </button>
        @endforeach
    </div>

    {{-- ── Search + program chips ─────────────────────────────────────────── --}}
    <div class="flex items-center gap-2 flex-wrap mb-4">
        <div class="relative flex-1 min-w-[240px] max-w-[420px]">
            <svg class="w-4 h-4 text-[#94a3b8] absolute left-3 top-1/2 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input x-model="search" type="text" placeholder="Filter by name, Medicaid ID, phone…"
                class="w-full pl-9 pr-3 py-2.5 bg-white border border-[#e2e8f0] rounded-[10px] text-sm font-medium text-[#1e293b] placeholder-[#94a3b8] outline-none focus:border-[#2563eb] transition-colors">
        </div>
        <button x-on:click="program = 'all'" :class="program === 'all' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#d8e2f0] hover:border-[#94a3b8]'" class="px-3.5 py-2 rounded-full text-sm font-semibold border transition-colors">Program: All</button>
        <button x-on:click="program = 'DHS'" :class="program === 'DHS' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#d8e2f0] hover:border-[#94a3b8]'" class="px-3.5 py-2 rounded-full text-sm font-semibold border transition-colors">DHS</button>
        <button x-on:click="program = 'MICH'" :class="program === 'MICH' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#d8e2f0] hover:border-[#94a3b8]'" class="px-3.5 py-2 rounded-full text-sm font-semibold border transition-colors">MICH</button>
    </div>

    {{-- ── Registry table ─────────────────────────────────────────────────── --}}
    <x-ui.panel bodyClass="p-0">
        <div class="w-full overflow-x-auto no-scrollbar">
            <table class="w-full min-w-[1100px] border-collapse">
                <thead>
                    <tr class="border-b border-[#eef2f9] bg-[#fafcff]">
                        @foreach(['Name','SSN','DOB (Age)','County','Program','MCO / Coordinator','Authorization','Caregiver','Last Compliance','Status'] as $col)
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#94a3b8] uppercase tracking-wider whitespace-nowrap">{{ $col }}</th>
                        @endforeach
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f1f5f9]">
                    <template x-for="r in paged" :key="r.id">
                        <tr class="hover:bg-[#f7faff] transition-colors cursor-pointer" x-on:click="window.location = '/clients/' + r.id">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full overflow-hidden shrink-0 border border-[#e6eef9]">
                                        <img :src="'https://ui-avatars.com/api/?name=' + encodeURIComponent(r.name) + '&background=eff4ff&color=2563eb&bold=true'" class="w-full h-full object-cover" alt="">
                                    </div>
                                    <span class="text-sm font-bold text-[#0f172a]" x-text="r.name"></span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-[#94a3b8] whitespace-nowrap">SSN &bull;&bull;&bull;&bull;&bull;&bull;</td>
                            <td class="px-4 py-3 text-sm font-medium text-[#1e293b] whitespace-nowrap">
                                <span x-text="r.dob || '—'"></span>
                                <span class="text-[#94a3b8]" x-show="r.age !== null">(<span x-text="r.age"></span>)</span>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-[#1e293b]" x-text="r.county || '—'"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 font-semibold rounded-full border text-xs px-2.5 py-0.5" :class="pillClass(r.program === 'DHS' ? 'indigo' : 'blue')" x-text="r.program_display || r.program"></span>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-[#475569] whitespace-nowrap" x-text="r.mco || '—'"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 font-semibold rounded-full border text-xs px-2.5 py-0.5" :class="pillClass(r.auth_tone)" x-text="r.auth_label"></span>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-[#1e293b] whitespace-nowrap" x-text="r.caregiver || '—'"></td>
                            <td class="px-4 py-3 text-sm font-medium text-[#94a3b8] whitespace-nowrap">—</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 font-semibold rounded-full border text-xs px-2.5 py-0.5" :class="pillClass(r.status_tone)" x-text="r.status"></span>
                            </td>
                            <td class="px-4 py-3 text-right" x-on:click.stop>
                                <div class="flex items-center justify-end gap-1.5">
                                    <a :href="'/clients/' + r.id" class="w-7 h-7 rounded-lg border border-[#e6eef9] flex items-center justify-center text-[#64748b] hover:text-[#2563eb] hover:border-[#2563eb] transition-colors" title="Open">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <form :action="'/clients/' + r.id" method="POST" x-on:submit.prevent="if(confirm('Remove this client?')) $el.submit()" class="m-0">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="w-7 h-7 rounded-lg border border-[#fbd5d5] flex items-center justify-center text-[#dc2626] hover:bg-[#fef2f2] transition-colors" title="Remove">
                                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="total === 0">
                        <td colspan="11" class="px-4 py-16 text-center">
                            <div class="text-sm font-semibold text-[#0f172a]">No clients match your filters</div>
                            <div class="text-sm text-[#94a3b8] mt-1">Adjust the search or status tab, or enrol a new client.</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-5 py-4 border-t border-[#eef2f9]">
            <p class="text-sm font-medium text-[#94a3b8]">
                Showing <span class="text-[#1e293b] font-bold" x-text="startEntry"></span>–<span class="text-[#1e293b] font-bold" x-text="endEntry"></span>
                of <span class="text-[#1e293b] font-bold" x-text="total"></span> clients
            </p>
            <div class="flex items-center gap-1.5">
                <button x-on:click="page > 1 && page--" :disabled="page === 1"
                    class="w-8 h-8 rounded-lg border border-[#e6eef9] bg-white text-[#475569] flex items-center justify-center hover:border-[#94a3b8] disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <template x-for="p in totalPages" :key="p">
                    <button x-on:click="page = p" :class="page === p ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e6eef9] hover:border-[#94a3b8]'" class="min-w-8 h-8 px-2 rounded-lg border text-sm font-bold transition-colors" x-text="p"></button>
                </template>
                <button x-on:click="page < totalPages && page++" :disabled="page === totalPages"
                    class="w-8 h-8 rounded-lg border border-[#e6eef9] bg-white text-[#475569] flex items-center justify-center hover:border-[#94a3b8] disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>
    </x-ui.panel>

</div>
@endsection

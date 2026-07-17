@extends('layouts.app')

@section('content')
<div class="space-y-6 pb-20 w-full px-2"
     x-data="{
        tab: 'monthly',
        showUploadModal: false,
        subjectType: 'Client',
        subjectId: '',
        subjectQuery: '',
        showSubjectOptions: false,
        subjects: { Client: @js($clientSubjects ?? []), Employee: @js($caregiverSubjects ?? []) },
        get activeSubjects() { return this.subjects[this.subjectType] || []; },
        get filteredSubjects() {
            const q = this.subjectQuery.trim().toLowerCase();
            if (!q) return this.activeSubjects.slice(0, 25);
            return this.activeSubjects.filter(s =>
                (s.label || '').toLowerCase().includes(q) || (s.meta || '').toLowerCase().includes(q) || String(s.id).includes(q)
            ).slice(0, 25);
        },
        resetSubject() { this.subjectId = ''; this.subjectQuery = ''; this.showSubjectOptions = false; },
        selectSubject(s) { this.subjectId = String(s.id); this.subjectQuery = s.label + ' (#' + s.id + ')'; this.showSubjectOptions = false; },
        onSubjectTypeChange() { this.resetSubject(); }
     }">

    {{-- Page header --}}
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 pt-2">
        <div>
            <h1 class="text-[28px] font-black text-[#1e293b] tracking-tight">Compliance &amp; Documents</h1>
            <p class="text-[12px] font-medium text-[#64748b] mt-1">
                {{ $cycleLabel }} cycle · {{ $monthlyKpis['received'] }} of {{ $monthlyKpis['total'] }} forms received · {{ $monthlyKpis['pending'] }} pending · {{ $monthlyKpis['late'] }} late
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-5 py-2.5 bg-white border border-[#e2e8f0] text-[#475569] rounded-xl text-[12px] font-bold shadow-sm flex items-center gap-2 cursor-default">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export
            </span>
            <button @click="showUploadModal = true" class="px-5 py-2.5 bg-[#2563eb] text-white rounded-xl text-[12px] font-bold shadow-lg shadow-blue-100 hover:bg-[#1d4ed8] flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Upload Document
            </button>
        </div>
    </div>

    {{-- Inner tabs --}}
    <div class="flex items-center gap-2">
        <button @click="tab='monthly'" :class="tab==='monthly' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#64748b] border-[#e2e8f0]'" class="px-4 py-2 rounded-xl text-[13px] font-bold border transition-all">Monthly Compliance</button>
        <button @click="tab='hub'" :class="tab==='hub' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#64748b] border-[#e2e8f0]'" class="px-4 py-2 rounded-xl text-[13px] font-bold border transition-all">Document Hub</button>
    </div>

    {{-- ════════════════ MONTHLY COMPLIANCE ════════════════ --}}
    <div x-show="tab==='monthly'" x-cloak class="space-y-6" x-data="complianceTracker(@js($tracker))">
        {{-- Cycle bar --}}
        <div class="flex items-center gap-3 bg-white border border-[#e2e8f0] rounded-xl px-4 py-2.5 flex-wrap">
            <span class="text-[10px] uppercase tracking-wider font-black text-[#94a3b8]">Compliance cycle</span>
            <span class="border border-[#cbd5e1] rounded-lg px-3 py-1.5 text-[13px] font-bold text-[#0f172a] inline-flex items-center gap-2">📅 {{ $cycleLabel }}</span>
            <span class="ml-auto text-[11px] text-[#94a3b8]">Viewing <b class="text-[#475569]">{{ $cycleLabel }}</b> — {{ $monthlyKpis['wellness_calls'] }} wellness call{{ $monthlyKpis['wellness_calls'] === 1 ? '' : 's' }} this cycle</span>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">
            @php
                $cKpis = [
                    ['Forms received', $monthlyKpis['received'], 'of '.$monthlyKpis['total'].' · '.$monthlyKpis['received_pct'].'%', 'green', 'received'],
                    ['Pending', $monthlyKpis['pending'], 'awaiting form', 'amber', 'pending'],
                    ['Late', $monthlyKpis['late'], 'roll to next batch', 'red', 'late'],
                    ['Wellness calls', $monthlyKpis['wellness_calls'], 'AI · Arabic/English', 'blue', 'all'],
                    ['Form submission rate', $monthlyKpis['received_pct'].'%', 'this monthly cycle', 'green', 'received'],
                ];
                $ct = ['green'=>'text-emerald-600','amber'=>'text-amber-600','red'=>'text-red-600','blue'=>'text-[#2563eb]'];
            @endphp
            @foreach($cKpis as [$label, $value, $sub, $color, $filter])
                <button @click="applyChip('{{ $filter }}')" class="text-left bg-[#eff6ff] p-4 rounded-[20px] border border-blue-100/50 shadow-sm hover:border-blue-300 transition-all">
                    <p class="text-[10px] font-bold text-[#64748b] uppercase tracking-wider leading-tight">{{ $label }}</p>
                    <p class="text-[26px] font-black {{ $ct[$color] }} leading-none mt-1.5">{{ $value }}</p>
                    <p class="text-[10px] font-medium text-[#94a3b8] mt-1">{{ $sub }}</p>
                </button>
            @endforeach
        </div>

        {{-- Progress --}}
        <div class="bg-white border border-[#e2e8f0] rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <b class="text-[14px] text-[#0f172a]">{{ $cycleLabel }} compliance progress</b>
                <span class="text-[12px] text-[#64748b]">Cycle closes — payroll batch 1st Tuesday</span>
            </div>
            @php
                $t = max(1, $monthlyKpis['total']);
                $rw = round($monthlyKpis['received'] / $t * 100);
                $lw = round($monthlyKpis['late'] / $t * 100);
                $pw = max(0, 100 - $rw - $lw);
            @endphp
            <div class="h-3 rounded-full bg-[#f1f5f9] overflow-hidden flex">
                <div class="h-full bg-emerald-500" style="width: {{ $rw }}%"></div>
                <div class="h-full bg-amber-500" style="width: {{ $lw }}%"></div>
                <div class="h-full bg-[#e2e8f0]" style="width: {{ $pw }}%"></div>
            </div>
            <div class="flex gap-5 mt-3 text-[11.5px] text-[#64748b]">
                <span><span class="inline-block w-2.5 h-2.5 rounded bg-emerald-500 mr-1.5 align-middle"></span>Received {{ $monthlyKpis['received'] }}</span>
                <span><span class="inline-block w-2.5 h-2.5 rounded bg-amber-500 mr-1.5 align-middle"></span>Late {{ $monthlyKpis['late'] }}</span>
                <span><span class="inline-block w-2.5 h-2.5 rounded bg-[#e2e8f0] mr-1.5 align-middle"></span>Pending {{ $monthlyKpis['pending'] }}</span>
            </div>
        </div>

        {{-- Filter chips --}}
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[260px]">
                <svg class="w-4 h-4 text-[#94a3b8] absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="search" @input="page=1" placeholder="Filter by client, caregiver, program…"
                    class="w-full pl-10 pr-4 py-2 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-semibold text-[#1e293b] placeholder:text-[#94a3b8] outline-none focus:ring-2 focus:ring-blue-500/10">
            </div>
            @foreach([['All','all'],['Received','received'],['Pending','pending'],['Late','late'],['DHS','dhs'],['MICH','mich']] as [$label, $key])
                <button @click="applyChip('{{ $key }}')"
                    :class="chip === '{{ $key }}' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0] hover:bg-gray-50'"
                    class="px-3.5 py-2 rounded-full border text-[11px] font-bold transition-all">{{ $label }}</button>
            @endforeach
        </div>

        {{-- Tracker table --}}
        <div class="bg-[#eff6ff] rounded-[24px] border border-blue-100/50 overflow-hidden shadow-sm">
            <div class="w-full overflow-x-auto no-scrollbar">
                <table class="w-full min-w-[860px] border-collapse">
                    <thead>
                        <tr class="bg-white border-b border-blue-100/20 text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">
                            <th class="px-6 py-3.5 text-left">Client</th>
                            <th class="px-4 py-3.5 text-left">Caregiver</th>
                            <th class="px-4 py-3.5 text-left">Program</th>
                            <th class="px-4 py-3.5 text-left">Wellness call</th>
                            <th class="px-4 py-3.5 text-left">Form ({{ now()->format('M') }})</th>
                            <th class="px-4 py-3.5 text-left">Days met</th>
                            <th class="px-4 py-3.5 text-left">Verified</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-blue-100/10">
                        <template x-for="r in paginated" :key="r.client + r.caregiver">
                            <tr class="hover:bg-blue-50/40 transition-colors">
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-7 h-7 rounded-lg bg-gradient-to-br from-slate-400 to-slate-600 text-white text-[10px] font-bold flex items-center justify-center" x-text="r.initials"></span>
                                        <span class="text-[12.5px] font-bold text-[#1e293b]" x-text="r.client"></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-[12px] font-medium text-[#475569]" x-text="r.caregiver"></td>
                                <td class="px-4 py-3.5">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold" :class="r.program === 'DHS' ? 'bg-slate-100 text-slate-600' : 'bg-blue-50 text-blue-700'" x-text="r.program_display || r.program"></span>
                                </td>
                                <td class="px-4 py-3.5 text-[12px] font-medium text-[#475569]">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full" :class="r.received ? 'bg-emerald-500' : (r.late ? 'bg-red-500' : 'bg-amber-500')"></span>
                                        <span x-text="r.received ? 'Done' : (r.late ? 'No answer' : 'Scheduled')"></span>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold"
                                        :class="r.received ? 'bg-emerald-50 text-emerald-700' : (r.late ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700')"
                                        x-text="r.received ? 'Received' : (r.late ? 'Late' : 'Pending')"></span>
                                </td>
                                <td class="px-4 py-3.5 text-[12px] font-medium text-[#475569]" x-text="r.received ? 'Met ✓' : '—'"></td>
                                <td class="px-4 py-3.5">
                                    <span x-show="r.received" class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700">Compliant</span>
                                    <span x-show="!r.received" class="text-[12px] text-[#94a3b8]">—</span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filtered.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-[#94a3b8] font-bold italic">No records match your filters.</td>
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

    {{-- ════════════════ DOCUMENT HUB ════════════════ --}}
    <div x-show="tab==='hub'" x-cloak class="space-y-6">
        {{-- Needs attention + search --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="bg-white border border-[#e2e8f0] rounded-2xl p-5">
                <h4 class="text-[13px] font-bold text-[#0f172a] mb-3">🔎 Search every document</h4>
                <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded-lg px-3 py-2.5 text-[12.5px] text-[#94a3b8] mb-3">Search across all clients &amp; caregivers — by name, type, folder…</div>
                <p class="text-[12px] text-[#64748b]">Same folder model as each profile — open a person to drill in. New uploads are auto-classified and filed by the parser.</p>
            </div>
            <div class="bg-white border border-[#e2e8f0] rounded-2xl p-5">
                <h4 class="text-[13px] font-bold text-[#0f172a] mb-3">⚠️ Needs attention</h4>
                @php
                    $na = [
                        ['Documents expired', $needsAttention['expired'], 'red'],
                        ['Pending review', $needsAttention['pending_review'], 'amber'],
                        ['Expiring ≤30d', $needsAttention['expiring'], 'amber'],
                        ['ICHAT expiring ≤30d', $needsAttention['ichat_expiring'], 'amber'],
                        ['Signed compliance forms', $needsAttention['signed_forms'], 'green'],
                    ];
                @endphp
                <div class="divide-y divide-[#f1f5f9]">
                    @foreach($na as [$label, $count, $tone])
                        <div class="flex items-center gap-3 py-2.5 text-[12.5px] text-[#475569]">
                            <span>{{ $label }}</span>
                            <span class="ml-auto px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $tone === 'red' ? 'bg-red-50 text-red-700' : ($tone === 'green' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700') }}">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Critical Issues --}}
        <div class="bg-white border border-[#e2e8f0] rounded-2xl p-5">
            <h4 class="text-[15px] font-bold text-[#0f172a] mb-4">Critical Issues (Expired / Expiring)</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead><tr class="border-b border-[#f1f5f9] text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">
                        <th class="pb-3">Document</th><th class="pb-3">Subject</th><th class="pb-3">Status</th><th class="pb-3 text-right">Download</th>
                    </tr></thead>
                    <tbody class="divide-y divide-[#f8fafc]">
                        @forelse($expired->merge($expiringSoon) as $doc)
                            <tr class="hover:bg-gray-50/50">
                                <td class="py-3 text-[12px] font-bold text-[#0f172a]">{{ $doc->name }}</td>
                                <td class="py-3 text-[12px] text-[#475569]">{{ $doc->documentable?->first_name }} {{ $doc->documentable?->last_name }}</td>
                                <td class="py-3"><span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $doc->isExpired() ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">{{ $doc->isExpired() ? 'Overdue' : 'Due' }}</span></td>
                                <td class="py-3 text-right"><a href="{{ route('documents.download', $doc->id) }}" class="text-[#2563eb] hover:text-[#1d4ed8] text-[11px] font-bold uppercase tracking-wider">Download</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-[#94a3b8] italic text-[12px]">No expired or expiring documents.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Signed e-Sign forms (from Forms module) --}}
        <div class="bg-white border border-[#e2e8f0] rounded-2xl p-5">
            <h4 class="text-[15px] font-bold text-[#0f172a] mb-4">Signed Compliance Forms (e-Sign)</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead><tr class="border-b border-[#f1f5f9] text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">
                        <th class="pb-3">Form</th><th class="pb-3">Person</th><th class="pb-3">Signed</th><th class="pb-3 text-right">Download</th>
                    </tr></thead>
                    <tbody class="divide-y divide-[#f8fafc]">
                        @forelse($signedComplianceForms as $submission)
                            <tr class="hover:bg-gray-50/50">
                                <td class="py-3 text-[12px] font-bold text-[#0f172a]">{{ $submission->template?->name }}</td>
                                <td class="py-3 text-[12px] text-[#475569]">{{ $submission->subjectName() }}</td>
                                <td class="py-3 text-[12px] text-[#64748b]">{{ $submission->signed_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="py-3 text-right">
                                    @if($submission->document_id)
                                        <a href="{{ route('forms.download', $submission->id) }}" class="text-[#2563eb] hover:text-[#1d4ed8] text-[11px] font-bold uppercase tracking-wider">Download</a>
                                    @else
                                        <span class="text-[11px] text-[#94a3b8]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-[#94a3b8] italic text-[12px]">No signed compliance forms yet — use Forms to collect e-signatures.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Verification Queue --}}
        <div class="bg-white border border-[#e2e8f0] rounded-2xl p-5">
            <h4 class="text-[15px] font-bold text-[#0f172a] mb-4">Verification Queue (Pending Approval)</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead><tr class="border-b border-[#f1f5f9] text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">
                        <th class="pb-3">Document</th><th class="pb-3">Subject</th><th class="pb-3 text-right">Action</th>
                    </tr></thead>
                    <tbody class="divide-y divide-[#f8fafc]">
                        @forelse($pendingVerification as $doc)
                            <tr class="hover:bg-gray-50/50">
                                <td class="py-3 text-[12px] font-bold text-[#0f172a]">{{ $doc->name }}</td>
                                <td class="py-3 text-[12px] text-[#475569]">{{ $doc->documentable?->first_name }} {{ $doc->documentable?->last_name }}</td>
                                <td class="py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('documents.download', $doc->id) }}" class="text-[#2563eb] hover:text-[#1d4ed8] text-[11px] font-bold uppercase tracking-wider">Download</a>
                                        <form action="{{ route('documents.verify', $doc->id) }}" method="POST">@csrf
                                            <button type="submit" class="text-emerald-600 hover:text-emerald-700 text-[11px] font-bold uppercase tracking-wider">Verify &amp; Approve</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-10 text-center text-[#94a3b8] italic text-[12px]">No documents pending verification.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- What this page does --}}
    <div class="rounded-2xl border border-dashed border-[#cbd5e1] bg-white p-5">
        <p class="text-[11px] font-black text-[#2563eb] uppercase tracking-wider mb-2">What this page does</p>
        <ul class="list-disc pl-5 space-y-1 text-[12.5px] text-[#475569]">
            <li>Agency-wide monthly compliance heartbeat — who has submitted this cycle's form across every client/caregiver (program-aware: DHS days met, MICH hours met).</li>
            <li>Document Hub rolls every profile's folders into one place with a needs-attention list; the per-profile Compliance &amp; Documents tabs are the source.</li>
            <li>Upload (with a real searchable subject picker) and Verify &amp; Approve work here and feed the same documents the profiles use.</li>
            <li class="text-[#94a3b8]"><b>Coming online:</b> wellness-call status and the month-picker connect to the RingCentral/Retell wellness-call cycle once those accounts are live.</li>
        </ul>
    </div>

    {{-- ════════════════ UPLOAD MODAL (preserved, with subject picker) ════════════════ --}}
    <div x-show="showUploadModal" x-cloak class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm" style="display:none;">
        <div class="w-full max-w-lg bg-white rounded-3xl shadow-2xl p-8" @click.away="showUploadModal = false">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-[#0f172a]">Upload Compliance Document</h3>
                <button @click="showUploadModal = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="space-y-5">
                    @if ($errors->any())
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-700">{{ collect($errors->all())->first() }}</div>
                    @endif
                    <div>
                        <label class="block mb-2 text-xs font-bold text-gray-400 uppercase">Document Name</label>
                        <input type="text" name="name" required placeholder="e.g. Drivers License, Medical Clearance" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:border-[#2563eb]">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-2 text-xs font-bold text-gray-400 uppercase">Subject Type</label>
                            <select name="documentable_type" x-model="subjectType" @change="onSubjectTypeChange()" required class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl">
                                <option value="Client">Client</option>
                                <option value="Employee">Caregiver</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-xs font-bold text-gray-400 uppercase">Subject</label>
                            <input type="hidden" name="documentable_id" :value="subjectId">
                            <div class="relative">
                                <input type="text" x-model="subjectQuery" @focus="showSubjectOptions = true" @input="subjectId = ''; showSubjectOptions = true" @click.away="showSubjectOptions = false" required placeholder="Search by name or ID" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl">
                                <div x-show="showSubjectOptions" x-cloak class="absolute z-20 mt-1 max-h-52 w-full overflow-y-auto rounded-xl border border-gray-100 bg-white shadow-xl">
                                    <template x-if="filteredSubjects.length === 0"><div class="px-3 py-2 text-xs text-gray-500">No matching records</div></template>
                                    <template x-for="subject in filteredSubjects" :key="subjectType + '-' + subject.id">
                                        <button type="button" @click="selectSubject(subject)" class="w-full px-3 py-2 text-left hover:bg-gray-50">
                                            <div class="text-xs font-semibold text-[#0f172a]" x-text="subject.label"></div>
                                            <div class="text-[11px] text-gray-500" x-text="(subject.meta || '') + (subject.meta ? ' · ' : '') + 'ID #' + subject.id"></div>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block mb-2 text-xs font-bold text-gray-400 uppercase">Select File</label>
                        <div class="relative flex items-center justify-center p-8 border-2 border-dashed border-gray-100 rounded-2xl bg-gray-50 hover:border-[#2563eb] transition-all cursor-pointer">
                            <input type="file" name="file" required class="absolute inset-0 opacity-0 cursor-pointer">
                            <div class="text-center">
                                <svg class="w-10 h-10 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                <p class="mt-2 text-xs text-gray-500">Drop file here or click to browse</p>
                            </div>
                        </div>
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="w-full py-4 text-sm font-bold text-white bg-[#2563eb] rounded-2xl hover:bg-[#1d4ed8] transition-all uppercase tracking-widest">Securely Upload</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function complianceTracker(rows) {
    return {
        rows: rows || [], search: '', chip: 'all', page: 1, perPage: 10,
        applyChip(key) { this.chip = (this.chip === key) ? 'all' : key; this.page = 1; },
        get filtered() {
            const s = this.search.toLowerCase();
            return this.rows.filter(r => {
                const matchesSearch = !s || (r.client + ' ' + r.caregiver + ' ' + r.program).toLowerCase().includes(s);
                let ok = true;
                switch (this.chip) {
                    case 'received': ok = !!r.received; break;
                    case 'pending':  ok = !r.received && !r.late; break;
                    case 'late':     ok = !!r.late; break;
                    case 'dhs':      ok = r.program === 'DHS'; break;
                    case 'mich':     ok = r.program === 'MICH'; break;
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

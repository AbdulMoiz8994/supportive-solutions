{{-- Audit Trail --}}
@php
    $pillBefore  = fn ($t) => '<span class="inline-flex items-center font-semibold rounded-full border text-xs px-2 py-0.5 bg-[#fef2f2] text-[#b42318] border-[#fecaca] whitespace-nowrap">'.$t.'</span>';
    $pillAfter   = fn ($t) => '<span class="inline-flex items-center font-semibold rounded-full border text-xs px-2 py-0.5 bg-[#ecfdf3] text-[#067647] border-[#d1fadf] whitespace-nowrap">'.$t.'</span>';
    $pillAdd     = fn ($t) => '<span class="inline-flex items-center font-semibold rounded-full border text-xs px-2 py-0.5 bg-[#ecfdf3] text-[#067647] border-[#d1fadf] whitespace-nowrap">'.$t.'</span>';
    $pillAmber   = fn ($t) => '<span class="inline-flex items-center font-semibold rounded-full border text-xs px-2 py-0.5 bg-[#fff8eb] text-[#b54708] border-[#fdecc8] whitespace-nowrap">'.$t.'</span>';
    $arrow       = '<svg class="w-3 h-3 text-[#94a3b8] inline-block mx-0.5 align-middle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
    $muted       = fn ($t) => '<span class="text-[#94a3b8]">'.$t.'</span>';

    $rows = [
        ['ts' => '2026-05-17 09:02:14', 'actor' => 'Authorizations Agent', 'role' => 'AI', 'atype' => 'ai', 'action' => 'Document uploaded', 'tone' => 'blue',
         'entity' => 'Documents › Authorizations', 'change' => $pillAdd('+ PA renewal notice (Aetna).pdf').' '.$muted('auto-filed, renewal task created'), 'src' => 'eFax · RingCentral'],
        ['ts' => '2026-05-12 14:31:08', 'actor' => 'Billing Agent', 'role' => 'AI', 'atype' => 'ai', 'action' => 'Claim status changed', 'tone' => 'dark',
         'entity' => 'Billing › Apr 2026 claim', 'change' => $pillAmber('Submitted').$arrow.$pillAfter('Paid · $3,240.00').' '.$muted('(EOB matched)'), 'src' => 'Availity (EOB)'],
        ['ts' => '2026-05-10 11:20:55', 'actor' => 'Ali Beydoun', 'role' => 'Owner', 'atype' => 'human', 'action' => 'Field edited', 'tone' => 'red',
         'entity' => 'Billing › Billing rate (MICH)', 'change' => $pillBefore('$28.00 / hr').$arrow.$pillAfter('$30.00 / hr'), 'src' => 'App (web) · 10.0.4.21'],
        ['ts' => '2026-04-30 17:55:10', 'actor' => 'Billing Agent', 'role' => 'AI', 'atype' => 'ai', 'action' => 'Claim submitted', 'tone' => 'blue',
         'entity' => 'Billing › Apr 2026 · 837P', 'change' => $muted('108 hrs @ $30 =').' '.$pillAfter('$3,240.00').' '.$muted('3 hospital days excluded'), 'src' => 'Availity'],
        ['ts' => '2026-04-22 13:08:40', 'actor' => 'Compliance Agent', 'role' => 'AI', 'atype' => 'ai', 'action' => 'Compliance form received', 'tone' => 'dark',
         'entity' => 'Compliance › Apr 2026', 'change' => $pillAmber('Awaiting').$arrow.$pillAfter('Received · 108 of 120 hrs').' '.$muted('(10–12 excluded)'), 'src' => 'Caregiver app'],
        ['ts' => '2026-04-12 09:30:11', 'actor' => 'AI Secretary', 'role' => 'AI · Arabic', 'atype' => 'ai', 'action' => 'Wellness call logged', 'tone' => 'red',
         'entity' => 'Communications', 'change' => $muted('Call recorded (4m12s) ·').' '.$pillAdd('concern flagged → task queue'), 'src' => 'RingCentral'],
        ['ts' => '2026-04-22 13:08:40', 'actor' => 'Ali Beydoun', 'role' => 'Owner', 'atype' => 'human', 'action' => 'PHI accessed (view)', 'tone' => 'dark',
         'entity' => 'Viewed › Demographics & Eligibility', 'change' => '', 'src' => 'App (web) · 10.0.4.21'],
        ['ts' => '2026-04-12 09:30:11', 'actor' => 'Background Checks Agent', 'role' => 'AI', 'atype' => 'ai', 'action' => 'Check re-run', 'tone' => 'dark',
         'entity' => 'Caregiver Yousef Hassan › SAM.gov & OIG', 'change' => $muted('Manual re-run requested').' '.$pillAfter('Clear').' '.$muted('(synced to caregiver profile)'), 'src' => 'App → SAM/OIG'],
        ['ts' => '2026-04-10 08:22:31', 'actor' => 'AI Secretary', 'role' => 'AI', 'atype' => 'ai', 'action' => 'Status flag added', 'tone' => 'red',
         'entity' => 'Hospitalization', 'change' => $pillAdd('+ Hospitalized 04/10').' '.$muted('flagged for billing exclusion'), 'src' => 'App → SAM/OIG'],
        ['ts' => '2026-02-01 10:05:47', 'actor' => 'Ali Beydoun', 'role' => 'Owner', 'atype' => 'human', 'action' => 'Approved · activated', 'tone' => 'blue',
         'entity' => 'Status', 'change' => $pillAmber('Pending Application').$arrow.$pillAfter('Active').' '.$muted('approved via Owner Approval Queue'), 'src' => 'Approval Queue'],
        ['ts' => '2026-01-30 15:12:03', 'actor' => 'Authorizations Agent', 'role' => 'AI', 'atype' => 'ai', 'action' => 'Exemption recorded', 'tone' => 'dark',
         'entity' => 'Live-In Exemption', 'change' => $pillAfter('Approved through Nov 2026').' '.$muted('EVV exempt'), 'src' => 'Upload'],
        ['ts' => '2026-01-28 11:47:19', 'actor' => 'Authorizations Agent', 'role' => 'AI', 'atype' => 'ai', 'action' => 'Authorization recorded', 'tone' => 'dark',
         'entity' => 'Authorizations', 'change' => $pillAdd('+ PA-2026-0042').' '.$muted('T1019 · valid → Jun 14, 2026'), 'src' => 'Upload (Aetna)'],
        ['ts' => '2026-01-12 10:33:50', 'actor' => 'R. Saleh', 'role' => 'Front desk', 'atype' => 'human', 'action' => 'Field edited', 'tone' => 'red',
         'entity' => 'Insurance & Eligibility', 'change' => $pillAdd('+ Health plan ID: AET-558920-01').' '.$muted('Insurance name: Aetna Better Health'), 'src' => 'App (web)'],
        ['ts' => '2026-01-09 14:22:08', 'actor' => 'R. Saleh', 'role' => 'Front desk', 'atype' => 'human', 'action' => 'Record created', 'tone' => 'dark',
         'entity' => 'Client chart', 'change' => $pillAfter('Eligibility verified (dual)').' '.$pillAdd('Program set: MICH').' '.$muted('status Pending Application'), 'src' => 'App (web)'],
    ];

    $actionToneClass = ['blue' => 'text-[#2563eb]', 'red' => 'text-[#dc2626]', 'dark' => 'text-[#0f172a]'];
@endphp

<div x-show="activeTab === 'audit'" x-cloak class="space-y-4">

    {{-- Read-only banner --}}
    <div class="rounded-2xl border border-[#cdddf5] bg-[#e8f0fc] p-5 flex items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
            <span class="w-11 h-11 rounded-xl bg-[#dbe7fa] text-[#2563eb] flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
            <div>
                <h3 class="text-base font-bold text-[#1d4ed8]">Read-only, tamper-proof log</h3>
                <p class="text-sm text-[#3b6cc4] mt-0.5">Entries can't be edited or deleted. Every change keeps before→after values; record views of PHI are logged too. Retained 7 years in the HIPAA-eligible cloud.</p>
            </div>
        </div>
        <x-ui.btn variant="outline" size="sm">Export for audit</x-ui.btn>
    </div>

    {{-- Filter bar --}}
    <div class="flex items-center gap-2.5 flex-wrap">
        <div class="relative">
            <svg class="w-4 h-4 text-[#94a3b8] absolute left-3 top-1/2 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            <input type="text" placeholder="Search the audit log…" class="w-[220px] pl-9 pr-3 py-2 rounded-[9px] border border-card-border bg-white text-sm outline-none focus:border-[#2563eb]">
        </div>
        @foreach(['From: Jan 1, 2026','To: May 31, 2026','Actor: All','Action: All','Field: All'] as $btn)
            <button type="button" class="inline-flex items-center gap-2 text-sm font-semibold text-[#475569] bg-white border border-card-border rounded-[9px] px-3 py-2 hover:border-[#94a3b8]">
                <svg class="w-3.5 h-3.5 text-[#94a3b8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>{{ $btn }}
                <svg class="w-3 h-3 text-[#94a3b8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
        @endforeach
        <x-ui.btn variant="primary" size="sm">Apply</x-ui.btn>
    </div>

    {{-- Table --}}
    <x-ui.panel bodyClass="p-0">
        <div class="w-full overflow-x-auto no-scrollbar">
            <table class="w-full min-w-[920px] border-collapse">
                <thead>
                    <tr class="border-b border-card-border bg-white/60">
                        @foreach(['Timestamp','Actor','Action','Entity / Change','Source',''] as $col)
                            <th class="px-5 py-3 text-left text-xs font-bold text-[#94a3b8] uppercase tracking-wider whitespace-nowrap">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-card-border">
                    @foreach($rows as $r)
                        @php [$d, $t] = explode(' ', $r['ts']); @endphp
                        <tr class="hover:bg-white/50 transition-colors align-top">
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="text-sm font-semibold text-[#0f172a]">{{ $d }}</div>
                                <div class="text-xs text-[#94a3b8]">{{ $t }}</div>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    @if($r['atype'] === 'ai')
                                        <span class="w-7 h-7 rounded-full bg-[#dbe7fa] text-[#2563eb] flex items-center justify-center shrink-0"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="7" width="16" height="12" rx="2"/><path d="M9 7V4h6v3"/></svg></span>
                                    @else
                                        <span class="w-7 h-7 rounded-full bg-[#c9d8ee] text-[#334155] text-xs font-bold flex items-center justify-center shrink-0">{{ strtoupper(mb_substr($r['actor'],0,1).mb_substr(explode(' ',$r['actor'].' ')[1],0,1)) }}</span>
                                    @endif
                                    <div>
                                        <div class="text-sm font-semibold text-[#0f172a]">{{ $r['actor'] }}</div>
                                        <div class="text-xs text-[#94a3b8]">{{ $r['role'] }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-sm font-semibold {{ $actionToneClass[$r['tone']] }}">{{ $r['action'] }}</td>
                            <td class="px-5 py-3.5">
                                <div class="text-sm text-[#475569]">{{ $r['entity'] }}</div>
                                @if($r['change'] !== '')<div class="mt-1 text-sm flex items-center flex-wrap gap-y-1">{!! $r['change'] !!}</div>@endif
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-sm text-[#64748b]">{{ $r['src'] }}</td>
                            <td class="px-5 py-3.5">
                                <span class="w-7 h-7 rounded-lg border border-card-border bg-white text-[#94a3b8] flex items-center justify-center"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-5 py-4 border-t border-card-border">
            <span class="text-sm text-[#94a3b8]">Showing {{ count($rows) }} of 312 entries · v1 → v36</span>
            <button type="button" class="text-sm font-semibold text-[#2563eb] hover:text-[#1d4ed8]">Load older entries ›</button>
        </div>
    </x-ui.panel>
</div>

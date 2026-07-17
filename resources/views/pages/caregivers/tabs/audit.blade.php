@php
    $c = $caregiver;
    $logs = $c->auditLogs->sortByDesc('occurred_at')->values();
@endphp

<div class="bg-blue-50/60 border border-blue-200/70 rounded-2xl px-5 py-4 mb-5 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <span class="w-11 h-11 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shrink-0"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
        <div>
            <p class="text-[14px] font-bold text-blue-700">Read-only, tamper-proof log</p>
            <p class="text-[12px] text-blue-600/80">Entries can't be edited or deleted. Every change keeps before→after values; record views of PHI are logged too. Retained 7 years in the HIPAA-eligible cloud.</p>
        </div>
    </div>
    <div class="flex gap-2 shrink-0">
        <a href="{{ route('caregivers.audit.export', $caregiver) }}" class="px-4 py-2 bg-white border border-blue-200 rounded-lg text-[11px] font-bold text-blue-700">Export CSV</a>
        <a href="{{ route('caregivers.audit.export-pdf', $caregiver) }}" class="px-4 py-2 bg-white border border-blue-200 rounded-lg text-[11px] font-bold text-blue-700">Export PDF</a>
    </div>
</div>

<div class="bg-white rounded-[20px] border border-[#e2e8f0] overflow-hidden">
    <table class="w-full text-[12px]">
        <thead><tr class="bg-[#f8fafc] text-[10px] font-black text-[#94a3b8] uppercase">
            <th class="px-5 py-3 text-left">Timestamp</th>
            <th class="px-5 py-3 text-left">Actor</th>
            <th class="px-5 py-3 text-left">Action</th>
            <th class="px-5 py-3 text-left">Entity / Change</th>
            <th class="px-5 py-3 text-left">Source</th>
            <th class="px-5 py-3"></th>
        </tr></thead>
        <tbody class="divide-y divide-[#f1f5f9]">
            @foreach($logs as $a)
            <tr class="hover:bg-blue-50/20 align-top">
                <td class="px-5 py-3.5 text-[#475569] whitespace-nowrap">{{ $a->occurred_at?->format('Y-m-d') }}<br><span class="text-[#94a3b8]">{{ $a->occurred_at?->format('H:i:s') }}</span></td>
                <td class="px-5 py-3.5">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full {{ $a->actor_type==='human' ? 'bg-blue-100 text-blue-700' : 'bg-violet-100 text-violet-600' }} flex items-center justify-center text-[10px] font-bold">{{ $a->actor_type==='human' ? strtoupper(Str::substr($a->actor_name,0,2)) : 'AI' }}</span>
                        <div><p class="font-bold text-[#1e293b]">{{ $a->actor_name }}</p><p class="text-[10px] text-[#94a3b8]">{{ $a->actor_role }}</p></div>
                    </div>
                </td>
                <td class="px-5 py-3.5 font-bold {{ in_array($a->action, ['Field edited','Activated & assigned','Check exemption added','Field set']) ? 'text-orange-600' : 'text-[#1e293b]' }}">{{ $a->action }}</td>
                <td class="px-5 py-3.5">
                    <p class="font-semibold text-[#475569]">{{ $a->entity }}</p>
                    <p class="mt-1">
                        @if($a->value_before)<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-50 text-red-500 line-through">{{ $a->value_before }}</span> <span class="text-[#94a3b8]">→</span> @endif
                        @if($a->value_after)<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-600">{{ $a->value_after }}</span>@endif
                        @if($a->detail)<span class="text-[11px] text-[#94a3b8]">{{ $a->detail }}</span>@endif
                    </p>
                </td>
                <td class="px-5 py-3.5 text-[#94a3b8]">{{ $a->source }}</td>
                <td class="px-5 py-3.5 text-center text-[#cbd5e1]">🔒</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="px-5 py-3 flex items-center justify-between border-t border-[#f1f5f9] text-[11px] font-bold text-[#94a3b8]">
        <span>Showing {{ $logs->count() }} entries</span>
        <span class="text-blue-600 cursor-pointer">Load older entries ›</span>
    </div>
</div>


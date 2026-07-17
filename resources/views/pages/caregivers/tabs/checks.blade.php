@php
    $c = $caregiver;
    $standard = $c->backgroundChecks->whereIn('type', ['CHAMPS','ICHAT','SAM','OIG']);
    $custom   = $c->backgroundChecks->whereIn('type', ['TB','MVR'])->merge($c->backgroundChecks->where('is_custom', true)->whereNotIn('type', ['TB','MVR']))->unique('id');
    $tone = fn($s) => match($s) {
        'Clear', 'On file' => 'bg-green-50 text-green-600',
        'Exempted' => 'bg-green-100 text-green-700',
        'Flagged' => 'bg-red-50 text-red-600',
        default => 'bg-orange-50 text-orange-500',
    };
    $allClear = $standard->where('status','Clear')->count() === $standard->count() && $standard->isNotEmpty();
@endphp

@if($allClear)
<div class="bg-green-50 border border-green-200 rounded-2xl px-5 py-3.5 mb-5 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <p class="text-[13px] font-bold text-green-700">All four checks clear · caregiver is eligible to work</p>
            <p class="text-[11px] text-green-600/80">The agent runs each on its cadence and re-checks automatically. You can re-run any on demand.</p>
        </div>
    </div>
    <button class="px-4 py-1.5 bg-white border border-green-200 rounded-lg text-[11px] font-bold text-green-700">Run all checks</button>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    @foreach($standard as $b)
    <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-[16px] font-bold text-[#1e293b]">{{ $b->label }}</h3>
                <p class="text-[11px] text-[#94a3b8]">{{ $b->cadence }}</p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $tone($b->status) }}">{{ $b->status }}</span>
        </div>
        <div class="space-y-2.5 text-[12px]">
            <div class="flex justify-between"><span class="text-[#94a3b8] font-medium">{{ $b->type === 'CHAMPS' ? 'Enrolled & associated' : 'Last run' }}</span><span class="font-bold text-[#1e293b]">{{ $b->last_run?->format('M j, Y') ?? '—' }}</span></div>
            <div class="flex justify-between"><span class="text-[#94a3b8] font-medium">{{ $b->type === 'CHAMPS' ? 'Provider ID' : 'Result' }}</span><span class="font-bold text-[#1e293b]">{{ $b->type === 'CHAMPS' ? ($b->provider_id ?? '—') : ($b->result ?? '—') }}</span></div>
            <div class="flex justify-between"><span class="text-[#94a3b8] font-medium">{{ $b->type === 'CHAMPS' ? 'Monitoring' : 'Next due' }}</span><span class="font-bold text-[#1e293b]">{{ $b->type === 'CHAMPS' ? ($b->monitoring ?? '—') : ($b->next_due?->format('M Y') ?? '—') }}</span></div>
            <div class="flex justify-between"><span class="text-[#94a3b8] font-medium">Source</span><span class="font-bold text-[#1e293b]">{{ $b->source ?? '—' }}</span></div>
        </div>
        <div class="flex items-center justify-between mt-4 pt-4 border-t border-[#f1f5f9]">
            <span class="text-[12px] font-bold text-blue-600 cursor-pointer">View letter</span>
            <div class="flex gap-2">
                <button class="px-3 py-1.5 bg-white border border-[#e2e8f0] rounded-lg text-[11px] font-bold text-[#475569]">⊘ Exempt</button>
                <button class="px-3 py-1.5 bg-white border border-[#e2e8f0] rounded-lg text-[11px] font-bold text-[#475569]">↻ Re-check</button>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Custom checks --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">
    @foreach($custom as $b)
    <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-[16px] font-bold text-[#1e293b]">{{ $b->label }}</h3>
                <p class="text-[11px] text-[#94a3b8]">Custom track · {{ $b->is_exempt ? 'special scenario' : 'added manually' }}</p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $tone($b->status) }}">{{ $b->status }}</span>
        </div>
        <div class="space-y-2.5 text-[12px]">
            @if($b->is_exempt)
                <div class="flex justify-between"><span class="text-[#94a3b8] font-medium">Status</span><span class="font-bold text-[#1e293b]">Exempt — not required for this caregiver</span></div>
                <div class="flex justify-between"><span class="text-[#94a3b8] font-medium">Reason</span><span class="font-bold text-[#1e293b]">{{ $b->exempt_reason }}</span></div>
                <div class="flex justify-between"><span class="text-[#94a3b8] font-medium">Approved by</span><span class="font-bold text-[#1e293b]">{{ $b->approved_by }} · {{ $b->approved_at?->format('M j, Y') }}</span></div>
            @else
                <div class="flex justify-between"><span class="text-[#94a3b8] font-medium">Type</span><span class="font-bold text-[#1e293b]">{{ $b->source }}</span></div>
                <div class="flex justify-between"><span class="text-[#94a3b8] font-medium">Date</span><span class="font-bold text-[#1e293b]">{{ $b->last_run?->format('M j, Y') ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-[#94a3b8] font-medium">Result</span><span class="font-bold text-[#1e293b]">{{ $b->result ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-[#94a3b8] font-medium">Reminder</span><span class="font-bold text-[#1e293b]">{{ $b->cadence ?? '—' }}</span></div>
            @endif
        </div>
    </div>
    @endforeach

    {{-- Add custom check tile --}}
    <div class="bg-blue-50/40 rounded-[20px] border-2 border-dashed border-blue-200 p-6 flex flex-col items-center justify-center text-center">
        <svg class="w-6 h-6 text-blue-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <p class="text-[14px] font-bold text-blue-600">Add other check</p>
        <p class="text-[11px] text-[#94a3b8] mt-1 max-w-[220px]">Track a custom or one-off check (TB test, driving record, drug screen…) — upload the document &amp; set an optional reminder.</p>
    </div>
</div>

{{-- Check history --}}
<div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6 mt-5">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-[15px] font-bold text-[#1e293b]">Check History</h3>
        <span class="text-[11px] font-bold text-[#94a3b8]">Newest first · stored 7 years</span>
    </div>
    <table class="w-full text-[12px]">
        <thead><tr class="text-[10px] font-black text-[#94a3b8] uppercase border-b border-[#f1f5f9]">
            <th class="py-2 text-left">Date</th><th class="py-2 text-left">Check</th><th class="py-2 text-left">Result</th><th class="py-2 text-left">Run by</th><th class="py-2 text-right">Result file</th>
        </tr></thead>
        <tbody class="divide-y divide-[#f1f5f9]">
            @foreach($c->backgroundChecks->sortByDesc('last_run')->take(8) as $b)
            <tr>
                <td class="py-2.5 font-semibold text-[#475569]">{{ $b->last_run?->format('M j, Y') ?? '—' }}</td>
                <td class="py-2.5 font-semibold text-[#1e293b]">{{ $b->label }}</td>
                <td class="py-2.5"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $tone($b->status) }}">{{ $b->status }}</span></td>
                <td class="py-2.5 text-[#475569]">{{ $b->is_custom ? 'R. Saleh' : 'Checks agent' }}</td>
                <td class="py-2.5 text-right text-blue-600 font-bold cursor-pointer">View</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="bg-orange-50/70 border border-orange-200 rounded-2xl px-5 py-4 mt-5">
    <p class="text-[12px] font-semibold text-orange-700">Flags are rare. If one comes back, it routes to you first to verify same-person by address/identity (false matches happen on common names). If confirmed, the caregiver is terminated &amp; replaced and any assignment closes. Until verified, the caregiver moves to On Hold rather than being auto-disqualified.</p>
</div>

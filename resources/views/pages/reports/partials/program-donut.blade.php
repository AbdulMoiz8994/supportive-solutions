@props(['split'])

@php
    $michPct = $split['mich_pct'] ?? 53;
@endphp

<div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
    <h3 class="text-[14px] font-semibold text-[#0f172a]">Revenue by program</h3>
    <p class="text-[12px] text-[#94a3b8] mt-0.5 mb-4">
        {{ $split['segments'][0]['rate'] ?? 30 ? '$'.number_format($split['segments'][0]['rate'] ?? 30, 0) : '$30' }}/hr MICH vs
        ${{ number_format($split['segments'][1]['rate'] ?? 27, 0) }}/hr DHS
    </p>
    <div class="flex items-center gap-5">
        <div class="w-32 h-32 rounded-full flex items-center justify-center shrink-0"
             style="background: conic-gradient(#2563eb 0 {{ $michPct }}%, #8b5cf6 {{ $michPct }}% 100%)">
            <div class="w-20 h-20 rounded-full bg-white flex flex-col items-center justify-center">
                <b class="text-lg text-[#0f172a]">{{ $split['total_label'] ?? '$0' }}</b>
                <span class="text-[10px] text-[#94a3b8]">billed</span>
            </div>
        </div>
        <div class="flex-1 space-y-2 text-[12.5px]">
            @foreach($split['segments'] as $seg)
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-sm {{ $seg['program'] === 'MICH' ? 'bg-[#2563eb]' : 'bg-[#8b5cf6]' }}"></span>
                    <span class="px-1.5 py-0.5 rounded text-[11px] font-bold {{ $seg['program'] === 'MICH' ? 'bg-[#dbeafe] text-[#1e40af]' : 'bg-[#ede9fe] text-[#5b21b6]' }}">{{ $seg['program'] }}</span>
                    <span class="text-[#64748b]">· {{ $seg['clients'] }} clients</span>
                    <span class="ml-auto font-bold text-[#0f172a]">${{ number_format($seg['amount']) }}</span>
                </div>
            @endforeach
            <div class="flex items-center pt-2 mt-1 border-t border-[#f1f5f9] text-[#64748b]">
                <span>Blended margin</span>
                <span class="ml-auto font-bold text-[#047857]">~{{ $split['blended_margin'] ?? 0 }}%</span>
            </div>
        </div>
    </div>
</div>

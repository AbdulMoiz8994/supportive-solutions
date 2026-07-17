@props(['bars', 'note' => null])

<div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
    <h3 class="text-[14px] font-semibold text-[#0f172a]">Compliance &amp; checks</h3>
    <p class="text-[12px] text-[#94a3b8] mt-0.5 mb-4">Forms + background monitoring</p>
    @foreach($bars as $bar)
        <div class="flex items-center gap-2.5 mb-2.5 text-[12px]">
            <span class="w-24 text-[#64748b] shrink-0">{{ $bar['label'] }}</span>
            <div class="flex-1 h-3.5 bg-[#f1f5f9] rounded-full overflow-hidden">
                <div class="h-full rounded-full {{ ($bar['pct'] ?? 0) >= 95 ? 'bg-[#10b981]' : 'bg-[#f59e0b]' }}" style="width: {{ min($bar['pct'] ?? 0, 100) }}%"></div>
            </div>
            <span class="w-16 text-right font-bold text-[#0f172a] shrink-0">{{ $bar['display'] }}</span>
        </div>
    @endforeach
    @if($note)
        <p class="text-[11.5px] text-[#94a3b8] mt-3">{{ $note }}</p>
    @endif
</div>

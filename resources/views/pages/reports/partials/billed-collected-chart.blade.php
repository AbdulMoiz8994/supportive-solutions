@props(['trend', 'footer' => null])

@php
    $max = max(collect($trend)->max('billed') ?: 1, 1);
@endphp

<div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
    <h3 class="text-[14px] font-semibold text-[#0f172a]">Billed vs collected — trailing 6 months</h3>
    <p class="text-[12px] text-[#94a3b8] mt-0.5 mb-4">Monthly billed (blue) and collected (green)</p>
    <div class="flex items-end gap-3 h-44 pt-2">
        @foreach($trend as $bar)
            @php $h = max(round(($bar['billed'] / $max) * 100), 8); @endphp
            <div class="flex-1 flex flex-col items-center gap-1.5 h-full justify-end">
                <div class="w-full flex items-end justify-center h-full">
                    <div class="w-[60%] flex flex-col-reverse rounded-t-md overflow-hidden" style="height: {{ $h }}%">
                        <div class="bg-[#10b981]" style="height: {{ $bar['billed'] > 0 ? round(($bar['collected'] / $bar['billed']) * 100) : 0 }}%"></div>
                        <div class="bg-[#2563eb] flex-1"></div>
                    </div>
                </div>
                <span class="text-[11px] text-[#64748b]">{{ $bar['label'] }}</span>
            </div>
        @endforeach
    </div>
    <div class="flex flex-wrap items-center gap-4 mt-3 text-[11.5px] text-[#475569]">
        <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-[#2563eb]"></span> Billed</span>
        <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-[#10b981]"></span> Collected</span>
        @if($footer)
            <span class="ml-auto text-[#94a3b8]">{{ $footer }}</span>
        @endif
    </div>
</div>

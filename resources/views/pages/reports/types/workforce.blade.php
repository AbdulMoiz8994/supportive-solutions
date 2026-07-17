@include('pages.reports.partials.kpi-row', ['kpis' => $data['kpis'], 'cols' => 5])

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
        <h3 class="text-[14px] font-semibold text-[#0f172a]">Roster composition</h3>
        <p class="text-[12px] text-[#94a3b8] mb-4">Family vs agency-sourced</p>
        @php $r = $data['roster']; @endphp
        <div class="flex items-center gap-5">
            <div class="w-32 h-32 rounded-full flex items-center justify-center shrink-0"
                 style="background: conic-gradient(#2563eb 0 {{ $r['family_pct'] }}%, #8b5cf6 {{ $r['family_pct'] }}% 100%)">
                <div class="w-20 h-20 rounded-full bg-white flex flex-col items-center justify-center">
                    <b class="text-lg">{{ $r['total'] }}</b>
                    <span class="text-[10px] text-[#94a3b8]">caregivers</span>
                </div>
            </div>
            <div class="flex-1 space-y-2 text-[12.5px]">
                <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm bg-[#2563eb]"></span> Family caregivers<span class="ml-auto font-bold">{{ $r['family'] }}</span></div>
                <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm bg-[#8b5cf6]"></span> Agency-sourced<span class="ml-auto font-bold">{{ $r['agency'] }}</span></div>
                <div class="flex items-center pt-2 border-t border-[#f1f5f9] text-[#64748b]"><span>Backdating eligible</span><span class="ml-auto font-bold">family only</span></div>
            </div>
        </div>
    </div>
    <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
        <h3 class="text-[14px] font-semibold text-[#0f172a]">Background status</h3>
        <p class="text-[12px] text-[#94a3b8] mb-4">4 checks across roster</p>
        @foreach($data['background_bars'] as $bar)
            <div class="flex items-center gap-2.5 mb-2.5 text-[12px]">
                <span class="w-20 text-[#64748b]">{{ $bar['label'] }}</span>
                <div class="flex-1 h-3.5 bg-[#f1f5f9] rounded-full overflow-hidden">
                    <div class="h-full rounded-full {{ $bar['pct'] >= 98 ? 'bg-[#10b981]' : ($bar['pct'] >= 90 ? 'bg-[#f59e0b]' : 'bg-[#ef4444]') }}" style="width: {{ min($bar['pct'], 100) }}%"></div>
                </div>
                <span class="w-16 text-right font-bold">{{ $bar['display'] }}</span>
            </div>
        @endforeach
    </div>
</div>

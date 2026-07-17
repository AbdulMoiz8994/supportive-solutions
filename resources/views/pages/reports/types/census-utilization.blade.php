@include('pages.reports.partials.kpi-row', ['kpis' => $data['kpis'], 'cols' => 5])

<div class="rounded-xl border border-[#e2e8f0] bg-white p-4 mb-4">
    <h3 class="text-[14px] font-semibold text-[#0f172a]">Active census — trailing 6 months</h3>
    <p class="text-[12px] text-[#94a3b8] mb-4">Total active clients</p>
    <div class="flex items-end gap-3 h-44">
        @foreach($data['census_trend'] as $bar)
            <div class="flex-1 flex flex-col items-center gap-1 h-full justify-end">
                <div class="w-[60%] bg-gradient-to-t from-[#2563eb] to-[#3b82f6] rounded-t-md" style="height: {{ max($bar['pct'], 8) }}%"></div>
                <span class="text-[11px] text-[#64748b]">{{ $bar['label'] }}</span>
                <span class="text-[10.5px] text-[#94a3b8]">{{ $bar['value'] }}</span>
            </div>
        @endforeach
    </div>
</div>

<div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden">
    <div class="px-4 py-3 border-b border-[#f1f5f9]">
        <h3 class="text-[14px] font-semibold text-[#0f172a]">Caregiver utilization bands</h3>
        <p class="text-[12px] text-[#94a3b8]">Monthly hours worked</p>
    </div>
    <table class="w-full text-left text-[13px]">
        <thead>
            <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                <th class="px-4 py-2.5">Band</th>
                <th class="px-4 py-2.5">Caregivers</th>
                <th class="px-4 py-2.5">Share</th>
                <th class="px-4 py-2.5">Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['bands'] as $band)
                @php
                    $pill = match($band['pill']) {
                        'blue' => 'bg-[#dbeafe] text-[#1e40af]',
                        'green' => 'bg-[#d1fae5] text-[#065f46]',
                        'amber' => 'bg-[#fef3c7] text-[#92400e]',
                        default => 'bg-[#e2e8f0] text-[#475569]',
                    };
                @endphp
                <tr class="border-b border-[#f1f5f9]">
                    <td class="px-4 py-2.5">{{ $band['band'] }}</td>
                    <td class="px-4 py-2.5">{{ $band['count'] }}</td>
                    <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-[11.5px] font-semibold {{ $pill }}">{{ $band['share'] }}</span></td>
                    <td class="px-4 py-2.5 text-[#64748b]">{{ $band['note'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

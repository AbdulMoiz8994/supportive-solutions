@include('pages.reports.partials.kpi-row', ['kpis' => $data['kpis'], 'cols' => 5])

<div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden">
    <div class="px-4 py-3 border-b border-[#f1f5f9]">
        <h3 class="text-[14px] font-semibold text-[#0f172a]">By agent</h3>
        <p class="text-[12px] text-[#94a3b8]">Handled · escalated · miss-rate (vs {{ $data['threshold'] }}% threshold)</p>
    </div>
    <table class="w-full text-left text-[13px]">
        <thead>
            <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                <th class="px-4 py-2.5">Agent</th>
                <th class="px-4 py-2.5">Tasks</th>
                <th class="px-4 py-2.5">Auto-handled</th>
                <th class="px-4 py-2.5">Escalated</th>
                <th class="px-4 py-2.5">Miss-rate</th>
                <th class="px-4 py-2.5">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['agents'] as $agent)
                @php $pill = $agent['pill'] === 'green' ? 'bg-[#d1fae5] text-[#065f46]' : 'bg-[#fef3c7] text-[#92400e]'; @endphp
                <tr class="border-b border-[#f1f5f9]">
                    <td class="px-4 py-2.5 font-semibold text-[#0f172a]">{{ $agent['name'] }}</td>
                    <td class="px-4 py-2.5">{{ $agent['tasks'] }}</td>
                    <td class="px-4 py-2.5">{{ $agent['auto_pct'] }}%</td>
                    <td class="px-4 py-2.5">{{ $agent['escalated'] }}</td>
                    <td class="px-4 py-2.5">{{ $agent['miss_rate'] }}%</td>
                    <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-[11.5px] font-semibold {{ $pill }}">{{ $agent['status'] }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="flex items-center justify-between px-4 py-3 border-t border-[#f1f5f9] text-[12px] text-[#94a3b8]">
        <span>{{ $data['footnote'] }}</span>
        <a href="{{ route('staff.index') }}" class="text-[#2563eb] font-semibold hover:underline">Open Staff &amp; AI Agents ›</a>
    </div>
</div>

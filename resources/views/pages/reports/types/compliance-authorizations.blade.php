@include('pages.reports.partials.kpi-row', ['kpis' => $data['kpis'], 'cols' => 5])

<div class="rounded-xl border border-[#e2e8f0] bg-white p-4 mb-4">
    <h3 class="text-[14px] font-semibold text-[#0f172a]">Monthly compliance — {{ $period->format('M') }}</h3>
    <p class="text-[12px] text-[#94a3b8] mb-4">Forms + verification</p>
    @foreach($data['bars'] as $bar)
        <div class="flex items-center gap-2.5 mb-2.5 text-[12px]">
            <span class="w-24 text-[#64748b]">{{ $bar['label'] }}</span>
            <div class="flex-1 h-3.5 bg-[#f1f5f9] rounded-full overflow-hidden">
                <div class="h-full rounded-full bg-[#10b981]" style="width: {{ min($bar['pct'], 100) }}%"></div>
            </div>
            <span class="w-16 text-right font-bold">{{ $bar['display'] }}</span>
        </div>
    @endforeach
</div>

<div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden">
    <div class="px-4 py-3 border-b border-[#f1f5f9]">
        <h3 class="text-[14px] font-semibold text-[#0f172a]">Authorizations expiring ≤30 days</h3>
        <p class="text-[12px] text-[#94a3b8]">Soonest first · service stops if no renewal</p>
    </div>
    <table class="w-full text-left text-[13px]">
        <thead>
            <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                <th class="px-4 py-2.5">Client</th>
                <th class="px-4 py-2.5">Program</th>
                <th class="px-4 py-2.5">Auth</th>
                <th class="px-4 py-2.5">Expires</th>
                <th class="px-4 py-2.5">Renewal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['expiring'] as $row)
                @php
                    $pill = match($row['pill']) {
                        'red' => 'bg-[#fee2e2] text-[#991b1b]',
                        'amber' => 'bg-[#fef3c7] text-[#92400e]',
                        'blue' => 'bg-[#dbeafe] text-[#1e40af]',
                        default => 'bg-[#e2e8f0] text-[#475569]',
                    };
                @endphp
                <tr class="border-b border-[#f1f5f9]">
                    <td class="px-4 py-2.5 font-semibold">{{ $row['client'] }}</td>
                    <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded text-[11px] font-bold {{ $row['program'] === 'MICH' ? 'bg-[#dbeafe] text-[#1e40af]' : 'bg-[#ede9fe] text-[#5b21b6]' }}">{{ $row['program'] }}</span></td>
                    <td class="px-4 py-2.5">{{ $row['auth'] }}</td>
                    <td class="px-4 py-2.5">{{ $row['expires'] }}</td>
                    <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-[11.5px] font-semibold {{ $pill }}">{{ $row['renewal'] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-[#94a3b8]">No authorizations expiring within 30 days.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t border-[#f1f5f9] text-right">
        <a href="{{ route('authorizations') }}" class="text-[12px] font-semibold text-[#2563eb] hover:underline">Open Authorizations ›</a>
    </div>
</div>

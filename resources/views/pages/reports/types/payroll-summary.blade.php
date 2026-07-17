@include('pages.reports.partials.kpi-row', ['kpis' => $data['kpis'], 'cols' => 5])

<div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden">
    <div class="px-4 py-3 border-b border-[#f1f5f9]">
        <h3 class="text-[14px] font-semibold text-[#0f172a]">By caregiver (sample)</h3>
        <p class="text-[12px] text-[#94a3b8]">Full roster exports to XLSX</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-[13px]">
            <thead>
                <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                    <th class="px-4 py-2.5">Caregiver</th>
                    <th class="px-4 py-2.5">Hours</th>
                    <th class="px-4 py-2.5">Wage</th>
                    <th class="px-4 py-2.5">Gross</th>
                    <th class="px-4 py-2.5">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($data['sample'] as $row)
                    @php
                        $pill = match($row['pill']) {
                            'green' => 'bg-[#d1fae5] text-[#065f46]',
                            'amber' => 'bg-[#fef3c7] text-[#92400e]',
                            default => 'bg-[#e2e8f0] text-[#475569]',
                        };
                    @endphp
                    <tr class="border-b border-[#f1f5f9]">
                        <td class="px-4 py-2.5 font-semibold text-[#0f172a]">{{ $row['name'] }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">{{ $row['hours'] !== null ? number_format((float)$row['hours'], 1) : '—' }}</td>
                        <td class="px-4 py-2.5">${{ number_format((float)$row['wage'], 0) }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">{{ $row['gross'] !== null ? '$'.number_format((float)$row['gross']) : '—' }}</td>
                        <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-[11.5px] font-semibold {{ $pill }}">{{ $row['status'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-[#94a3b8]">No payroll records for this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-[#f1f5f9] text-right">
        <a href="{{ route('payroll', ['period' => $period->format('Y-m')]) }}" class="text-[12px] font-semibold text-[#2563eb] hover:underline">Open Payroll run ›</a>
    </div>
</div>

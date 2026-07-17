@include('pages.reports.partials.kpi-row', ['kpis' => $data['kpis'], 'cols' => 4])

<div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden">
    <div class="px-4 py-3 border-b border-[#f1f5f9]">
        <h3 class="text-[14px] font-semibold text-[#0f172a]">Top rejection reasons</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-[13px]">
            <thead>
                <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                    <th class="px-4 py-2.5">Reason</th>
                    <th class="px-4 py-2.5">Count</th>
                    <th class="px-4 py-2.5">$ impact</th>
                    <th class="px-4 py-2.5">Channel</th>
                    <th class="px-4 py-2.5">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($data['reasons'] as $row)
                    @php $pill = $row['pill'] === 'green' ? 'bg-[#d1fae5] text-[#065f46]' : 'bg-[#fef3c7] text-[#92400e]'; @endphp
                    <tr class="border-b border-[#f1f5f9]">
                        <td class="px-4 py-2.5">{{ $row['reason'] }}</td>
                        <td class="px-4 py-2.5">{{ $row['count'] }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row['impact']) }}</td>
                        <td class="px-4 py-2.5">{{ $row['channel'] }}</td>
                        <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-[11.5px] font-semibold {{ $pill }}">{{ $row['status'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-[#94a3b8]">No rejections in the trailing 90 days — clean-claim rate holding.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="flex items-center justify-between px-4 py-3 border-t border-[#f1f5f9] text-[12px] text-[#94a3b8]">
        <span>{{ $data['footnote'] }}</span>
        <a href="{{ route('billing-claims-audit.index', ['status' => 'rejected', 'period' => $period->format('Y-m')]) }}" class="text-[#2563eb] font-semibold hover:underline">Open rejected in Billing ›</a>
    </div>
</div>

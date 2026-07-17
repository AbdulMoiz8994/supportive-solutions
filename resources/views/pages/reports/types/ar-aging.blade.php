@include('pages.reports.partials.kpi-row', ['kpis' => $data['kpis'], 'cols' => 5])

@include('pages.reports.partials.aging-panel', [
    'title' => 'Aging distribution',
    'subtitle' => 'Share of outstanding by bucket',
    'buckets' => $data['buckets'],
    'footnote' => $data['footnote'],
    'link' => route('billing-claims-audit.aging', ['period' => $period->format('Y-m')]),
    'linkLabel' => 'Open Billing aging',
])

<div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden mt-4">
    <div class="px-4 py-3 border-b border-[#f1f5f9]">
        <h3 class="text-[14px] font-semibold text-[#0f172a]">Aging by payer &amp; program</h3>
        <p class="text-[12px] text-[#94a3b8]">MICH (Availity/EOB) vs DHS (Sigma)</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-[13px]">
            <thead>
                <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                    <th class="px-4 py-2.5">Payer / program</th>
                    <th class="px-4 py-2.5">0–30d</th>
                    <th class="px-4 py-2.5">31–60d</th>
                    <th class="px-4 py-2.5">61–90d</th>
                    <th class="px-4 py-2.5">90+d</th>
                    <th class="px-4 py-2.5">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($data['by_payer'] as $row)
                    <tr class="border-b border-[#f1f5f9]">
                        <td class="px-4 py-2.5">
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ str_contains($row['program'], 'MICH') ? 'bg-[#dbeafe] text-[#1e40af]' : 'bg-[#ede9fe] text-[#5b21b6]' }}">{{ $row['program'] }}</span>
                            {{ str_replace($row['program'].' · ', '', $row['label']) }}
                        </td>
                        @foreach(['current', '31_60', '61_90', '90_plus', 'total'] as $col)
                            <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row[$col] ?? 0) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-[#94a3b8]">No outstanding receivables for this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('pages.reports.partials.kpi-row', ['kpis' => $data['kpis'], 'cols' => 4])

<div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden">
    <div class="px-4 py-3 border-b border-[#f1f5f9]">
        <h3 class="text-[14px] font-semibold text-[#0f172a]">Margin by program</h3>
        <p class="text-[12px] text-[#94a3b8]">Per-hour spread = billing rate − ${{ number_format($data['rates']['wage'] ?? 15, 0) }} wage</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-[13px]">
            <thead>
                <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                    <th class="px-4 py-2.5">Program</th>
                    <th class="px-4 py-2.5">Hours</th>
                    <th class="px-4 py-2.5">Bill rate</th>
                    <th class="px-4 py-2.5">Wage</th>
                    <th class="px-4 py-2.5">Spread/hr</th>
                    <th class="px-4 py-2.5">Revenue</th>
                    <th class="px-4 py-2.5">Wages</th>
                    <th class="px-4 py-2.5">Margin</th>
                    <th class="px-4 py-2.5">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['rows'] as $row)
                    <tr class="border-b border-[#f1f5f9]">
                        <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded text-[11px] font-bold {{ $row['program'] === 'MICH' ? 'bg-[#dbeafe] text-[#1e40af]' : 'bg-[#ede9fe] text-[#5b21b6]' }}">{{ $row['program'] }}</span></td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">{{ number_format($row['hours']) }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row['bill_rate'], 0) }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row['wage'], 0) }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row['spread'], 0) }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row['revenue']) }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row['wages']) }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row['margin']) }}</td>
                        <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-[11.5px] font-semibold bg-[#d1fae5] text-[#065f46]">{{ $row['margin_pct'] }}%</span></td>
                    </tr>
                @endforeach
                @php $b = $data['blended']; @endphp
                <tr class="bg-[#fcfdfe] font-bold">
                    <td class="px-4 py-2.5">Blended</td>
                    <td class="px-4 py-2.5 font-semibold tabular-nums">{{ number_format($b['hours']) }}</td>
                    <td class="px-4 py-2.5">—</td>
                    <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($data['rates']['wage'], 0) }}</td>
                    <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($b['spread'], 2) }}</td>
                    <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($b['revenue']) }}</td>
                    <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($b['wages']) }}</td>
                    <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($b['margin']) }}</td>
                    <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-[11.5px] font-semibold bg-[#d1fae5] text-[#065f46]">{{ $b['margin_pct'] }}%</span></td>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="px-4 py-3 border-t border-[#f1f5f9] text-[12px] text-[#94a3b8]">MICH carries the higher spread. Rates are editable per program in Billing &amp; Global Settings.</p>
</div>

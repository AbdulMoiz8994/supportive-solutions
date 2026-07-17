@include('pages.reports.partials.month-bar', [
    'period' => $period,
    'preset' => 'month',
    'periodOptions' => [['preset' => 'month', 'label' => $data['period_label'] ?? $period->format('M Y')]],
    'prevPeriod' => $period->copy()->subMonth(),
    'nextPeriod' => $period->copy()->addMonth(),
    'routeName' => 'reports.show',
    'queryParams' => fn ($extra = []) => array_merge(['report' => $report], request()->query(), $extra),
    'showProgramFilter' => true,
    'program' => $data['program_filter'] ?? 'all',
])

@include('pages.reports.partials.kpi-row', ['kpis' => $data['kpis'], 'cols' => 4])

<div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden mb-4">
    <div class="px-4 py-3 border-b border-[#f1f5f9]">
        <h3 class="text-[14px] font-semibold text-[#0f172a]">By month</h3>
        <p class="text-[12px] text-[#94a3b8]">All programs combined</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                    <th class="px-4 py-2.5">Month</th>
                    <th class="px-4 py-2.5">Billed</th>
                    <th class="px-4 py-2.5">Collected</th>
                    <th class="px-4 py-2.5">Outstanding</th>
                    <th class="px-4 py-2.5">Collection rate</th>
                    <th class="px-4 py-2.5">Claims</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['months'] as $row)
                    @php
                        $pill = match($row['rate_pill']) {
                            'green' => 'bg-[#d1fae5] text-[#065f46]',
                            'amber' => 'bg-[#fef3c7] text-[#92400e]',
                            default => 'bg-[#fee2e2] text-[#991b1b]',
                        };
                    @endphp
                    <tr class="border-b border-[#f1f5f9]">
                        <td class="px-4 py-2.5 font-semibold text-[#0f172a]">{{ $row['month'] }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row['billed']) }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row['collected']) }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row['outstanding']) }}</td>
                        <td class="px-4 py-2.5">
                            <span class="px-2 py-0.5 rounded-full text-[11.5px] font-semibold {{ $pill }}">{{ $row['rate'] }}%</span>
                            @if($row['in_flight'] ?? false)
                                <span class="block text-[10.5px] text-[#94a3b8]">in flight</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">{{ $row['claims'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden">
    <div class="px-4 py-3 border-b border-[#f1f5f9]">
        <h3 class="text-[14px] font-semibold text-[#0f172a]">By program — {{ $period->format('M Y') }}</h3>
        <p class="text-[12px] text-[#94a3b8]">MICH bills ${{ number_format($data['by_program'][0]['rate'] ?? 30, 0) }}/hr · DHS bills ${{ number_format($data['by_program'][1]['rate'] ?? 27, 0) }}/hr</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                    <th class="px-4 py-2.5">Program</th>
                    <th class="px-4 py-2.5">Clients</th>
                    <th class="px-4 py-2.5">Hours billed</th>
                    <th class="px-4 py-2.5">Rate</th>
                    <th class="px-4 py-2.5">Billed</th>
                    <th class="px-4 py-2.5">Collected</th>
                    <th class="px-4 py-2.5">Channel</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['by_program'] as $row)
                    <tr class="border-b border-[#f1f5f9]">
                        <td class="px-4 py-2.5">
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ $row['program'] === 'MICH' ? 'bg-[#dbeafe] text-[#1e40af]' : 'bg-[#ede9fe] text-[#5b21b6]' }}">{{ $row['program'] }}</span>
                        </td>
                        <td class="px-4 py-2.5">{{ $row['clients'] }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">{{ number_format($row['hours']) }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row['rate'], 0) }}/hr</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row['billed']) }}</td>
                        <td class="px-4 py-2.5 font-semibold tabular-nums">${{ number_format($row['collected']) }}</td>
                        <td class="px-4 py-2.5 text-[13px]">{{ $row['channel'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="flex items-center justify-between px-4 py-3 border-t border-[#f1f5f9] text-[12px] text-[#94a3b8]">
        <span>{{ $data['footnote'] }}</span>
        <a href="{{ route('reports.show', ['report' => 'ar-aging', 'period' => $period->format('Y-m')]) }}" class="text-[#2563eb] font-semibold hover:underline">Open AR aging ›</a>
    </div>
</div>

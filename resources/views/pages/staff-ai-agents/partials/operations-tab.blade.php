<div class="grid grid-cols-1 xl:grid-cols-[1.3fr_1fr] gap-4 mb-4">
    {{-- Miss-rate chart --}}
    <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
        <h4 class="text-[12px] font-bold uppercase tracking-wide text-[#2563eb] mb-2">
            Fleet miss-rate — last 12 weeks (ceiling {{ $ceiling }}%)
        </h4>
        <div class="border-t-2 border-dashed border-[#ef4444] text-[10px] text-[#b91c1c] pt-1 mb-2">
            — — — {{ $ceiling }}% auto-pause threshold — — —
        </div>
        <div class="flex items-end gap-2 h-[120px] mt-2">
            @foreach($missRateChart as $week)
                <div class="flex-1 flex flex-col items-center justify-end gap-1.5 h-full">
                    <div class="w-[62%] rounded-t-md bg-gradient-to-t from-[#10b981] to-[#34d399]"
                         style="height: {{ max($week['height_pct'], 8) }}%;"></div>
                    <span class="text-[10px] text-[#94a3b8]">{{ $week['label'] }}</span>
                </div>
            @endforeach
        </div>
        <p class="text-[11.5px] text-[#94a3b8] mt-2">
            Fleet has stayed well under {{ $ceiling }}% all quarter. Crossing it auto-pauses the offending agent and alerts you.
        </p>
    </div>

    {{-- Alerts feed --}}
    <div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
        <h4 class="text-[12px] font-bold uppercase tracking-wide text-[#2563eb] mb-3">Active alerts &amp; events</h4>
        <div class="space-y-3">
            @foreach($alerts as $alert)
                @php
                    $dot = match($alert['tone'] ?? 'ok') {
                        'esc' => 'bg-[#f59e0b]',
                        'now' => 'bg-[#2563eb]',
                        default => 'bg-[#10b981]',
                    };
                @endphp
                <div class="flex gap-2.5 text-[12.5px]">
                    <span class="w-2 h-2 rounded-full {{ $dot }} mt-1.5 shrink-0"></span>
                    <div>
                        <p class="font-semibold text-[#0f172a]">
                            {{ $alert['title'] }}
                            @if(!empty($alert['detail']))
                                <span class="font-normal text-[#475569]"> — {{ $alert['detail'] }}</span>
                            @endif
                        </p>
                        <p class="text-[11px] text-[#94a3b8] mt-0.5">{{ $alert['time'] }}</p>
                        @if(!empty($alert['link']))
                            <a href="{{ $alert['link'] }}" class="text-[11px] text-[#2563eb] font-semibold hover:underline mt-1 inline-block">Open queue →</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Leaderboard --}}
<div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden">
    <div class="px-4 py-3 border-b border-[#f1f5f9]">
        <h3 class="text-[14px] font-semibold text-[#0f172a]">Agent leaderboard</h3>
        <p class="text-[12px] text-[#94a3b8] mt-0.5">Tasks · auto-handled · escalated · miss-rate · status</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                    <th class="px-4 py-2.5">Agent</th>
                    <th class="px-4 py-2.5">Tasks (May)</th>
                    <th class="px-4 py-2.5">Auto</th>
                    <th class="px-4 py-2.5">Escalated</th>
                    <th class="px-4 py-2.5">Miss-rate</th>
                    <th class="px-4 py-2.5">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($leaderboard as $row)
                    @php
                        $pill = $row['pill'] === 'amber'
                            ? 'bg-[#fef3c7] text-[#92400e]'
                            : 'bg-[#d1fae5] text-[#065f46]';
                    @endphp
                    <tr class="border-b border-[#f1f5f9] hover:bg-[#f8fafc] cursor-pointer"
                        onclick="window.location='{{ route('staff.agents.show', $row['slug']) }}'">
                        <td class="px-4 py-2.5 font-semibold text-[#0f172a]">{{ $row['name'] }}</td>
                        <td class="px-4 py-2.5 tabular-nums">{{ $row['tasks'] }}</td>
                        <td class="px-4 py-2.5">{{ $row['auto_pct'] }}</td>
                        <td class="px-4 py-2.5 tabular-nums">{{ $row['escalated'] }}</td>
                        <td class="px-4 py-2.5 {{ $row['miss_warn'] ? 'text-[#b45309] font-semibold' : '' }}">{{ $row['miss_rate'] }}</td>
                        <td class="px-4 py-2.5">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11.5px] font-semibold {{ $pill }}">{{ $row['status'] }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@include('pages.reports.partials.kpi-row', ['kpis' => $data['kpis'] ?? [], 'cols' => $cols ?? 4])

@foreach($data['sections'] ?? [] as $section)
    <div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden mb-4">
        <div class="px-4 py-3 border-b border-[#f1f5f9]">
            <h3 class="text-[14px] font-semibold text-[#0f172a]">{{ $section['title'] ?? 'Section' }}</h3>
            @if(!empty($section['subtitle']))
                <p class="text-[12px] text-[#94a3b8]">{{ $section['subtitle'] }}</p>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[13px]">
                @if(!empty($section['headers']))
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                            @foreach($section['headers'] as $header)
                                <th class="px-4 py-2.5 font-semibold">{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                @endif
                <tbody>
                    @forelse($section['rows'] ?? [] as $row)
                        <tr class="border-b border-[#f1f5f9]">
                            @foreach(is_array($row) ? (array_is_list($row) ? $row : array_values($row)) : [$row] as $cell)
                                <td class="px-4 py-2.5 text-[#334155]">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ max(count($section['headers'] ?? []), 1) }}" class="px-4 py-6 text-center text-[#94a3b8]">No rows for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endforeach

@if(!empty($data['footnote']))
    <p class="text-[12px] text-[#64748b] rounded-xl border border-dashed border-[#cbd5e1] bg-white px-4 py-3">{{ $data['footnote'] }}</p>
@elseif(!empty($data['note']))
    <p class="text-[13px] text-[#64748b] rounded-xl border border-dashed border-[#cbd5e1] bg-white px-4 py-3">{{ $data['note'] }}</p>
@endif

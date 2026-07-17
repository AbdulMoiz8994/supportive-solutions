@include('pages.reports.partials.kpi-row', ['kpis' => $data['kpis'] ?? [], 'cols' => 4])

@if(!empty($data['note']))
    <p class="text-[13px] text-[#64748b] rounded-xl border border-dashed border-[#cbd5e1] bg-white px-4 py-3">{{ $data['note'] }}</p>
@endif

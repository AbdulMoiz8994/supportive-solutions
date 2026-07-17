@props(['kpis', 'cols' => 5])

@php
    $grid = match((int)$cols) {
        4 => 'xl:grid-cols-4',
        5 => 'xl:grid-cols-5',
        default => 'xl:grid-cols-'.(int)$cols,
    };
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 {{ $grid }} gap-3">
    @foreach($kpis as $kpi)
        @php
            $valueClass = match($kpi['tone'] ?? 'default') {
                'ok' => 'text-[#047857]',
                'alert' => 'text-[#b45309]',
                'danger' => 'text-[#b91c1c]',
                default => 'text-[#0f172a]',
            };
        @endphp
        <div class="rounded-xl border border-[#e2e8f0] bg-white px-3.5 py-3">
            <div class="text-[11.5px] text-[#64748b] mb-1">{{ $kpi['label'] }}</div>
            <div class="text-[20px] font-bold leading-tight {{ $valueClass }}">{{ $kpi['value'] }}</div>
            @if(!empty($kpi['sub']))
                <div class="text-[11px] text-[#94a3b8] mt-1">{{ $kpi['sub'] }}</div>
            @endif
        </div>
    @endforeach
</div>

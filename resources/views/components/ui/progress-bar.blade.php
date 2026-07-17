@props([
    'label' => '',
    'value' => 0,        // 0 - 100
    'display' => null,    // text shown on the right (defaults to value%)
    'color' => '#22c55e', // bar fill colour
    'track' => '#e6eef9', // track colour
])

@php $pct = max(0, min(100, (float) $value)); @endphp

<div class="flex items-center gap-4">
    <span class="text-[12px] font-medium text-[#64748b] w-[120px] shrink-0">{{ $label }}</span>
    <div class="flex-1 h-2.5 rounded-full overflow-hidden" style="background: {{ $track }}">
        <div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $color }}"></div>
    </div>
    <span class="text-[12px] font-bold text-[#0f172a] w-[40px] text-right shrink-0">{{ $display ?? (round($pct).'%') }}</span>
</div>

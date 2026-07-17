@props([
    'label' => '',
    'value' => '',
    'sub' => null,
    'valueClass' => 'text-[#0f172a]',
])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-[#e6eef9] bg-[#f7fbff] px-4 py-3.5']) }}>
    <div class="flex items-center gap-2 mb-2.5">
        <span class="w-7 h-7 rounded-[8px] bg-white border border-[#e6eef9] flex items-center justify-center text-[#94a3b8]">
            <svg class="w-[15px] h-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="4" rx="1.5"/><rect x="14" y="11" width="7" height="10" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
        </span>
        <span class="text-[11.5px] font-medium text-[#64748b] leading-tight">{{ $label }}</span>
    </div>
    <div class="text-[26px] font-extrabold leading-none tracking-tight {{ $valueClass }}">{{ $value }}</div>
    @if($sub)
        <div class="text-[11px] font-medium text-[#94a3b8] mt-2">{{ $sub }}</div>
    @endif
</div>

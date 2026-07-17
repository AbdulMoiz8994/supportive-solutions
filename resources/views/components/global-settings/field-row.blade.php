@props(['label', 'hint' => null, 'align' => 'center'])

<div @class([
    'grid grid-cols-1 md:grid-cols-[220px_1fr] gap-2 md:gap-4 py-3.5 border-b border-slate-50 last:border-0',
    'items-center' => $align === 'center',
    'items-start' => $align === 'start',
])>
    <div>
        <b class="text-sm font-bold text-[#1e293b]">{{ $label }}</b>
        @if($hint)
            <span class="block text-[11px] text-[#94a3b8] font-semibold mt-0.5">{{ $hint }}</span>
        @endif
    </div>
    <div>{{ $slot }}</div>
</div>

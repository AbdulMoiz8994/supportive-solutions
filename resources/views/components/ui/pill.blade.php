@props([
    'variant' => 'gray',   // gray | blue | green | amber | red | indigo
    'size' => 'sm',        // xs | sm
])

@php
    $variants = [
        'gray'   => 'bg-[#f1f5f9] text-[#475569] border-[#e2e8f0]',
        'blue'   => 'bg-[#eff4ff] text-[#2563eb] border-[#dbe6ff]',
        'green'  => 'bg-[#ecfdf3] text-[#067647] border-[#d1fadf]',
        'amber'  => 'bg-[#fff8eb] text-[#b54708] border-[#fdecc8]',
        'red'    => 'bg-[#fef3f2] text-[#d92d20] border-[#fee4e2]',
        'indigo' => 'bg-[#eef2ff] text-[#4338ca] border-[#e0e7ff]',
    ];
    $sizes = [
        'xs' => 'text-[10px] px-2 py-0.5',
        'sm' => 'text-[11px] px-2.5 py-0.5',
    ];
    $classes = ($variants[$variant] ?? $variants['gray']).' '.($sizes[$size] ?? $sizes['sm']);
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 font-semibold rounded-full border whitespace-nowrap $classes"]) }}>
    {{ $slot }}
</span>

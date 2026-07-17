@props([
    'variant' => 'outline',   // primary | outline | ghost | danger | success
    'size' => 'md',           // sm | md
    'href' => null,
    'type' => 'button',
    'icon' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-1.5 font-semibold rounded-[9px] transition-all duration-150 whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed';

    $sizes = [
        'sm' => 'text-xs px-2.5 py-1.5',
        'md' => 'text-sm px-3.5 py-2',
    ];

    $variants = [
        'primary' => 'bg-[#2563eb] text-white border border-[#2563eb] hover:bg-[#1d4ed8] shadow-[0_2px_8px_rgba(37,99,235,0.25)]',
        'outline' => 'bg-white text-[#475569] border border-[#d8e2f0] hover:border-[#94a3b8] hover:text-[#1e293b]',
        'ghost'   => 'bg-transparent text-[#475569] border border-transparent hover:bg-[#eef4ff] hover:text-[#2563eb]',
        'danger'  => 'bg-white text-[#dc2626] border border-[#fbd5d5] hover:bg-[#fef2f2]',
        'success' => 'bg-[#16a34a] text-white border border-[#16a34a] hover:bg-[#15803d] shadow-[0_2px_8px_rgba(22,163,74,0.25)]',
    ];

    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['outline']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<span class="shrink-0">{!! $icon !!}</span>@endif{{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<span class="shrink-0">{!! $icon !!}</span>@endif{{ $slot }}
    </button>
@endif

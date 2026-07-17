@props([
    'label' => '',
    'value' => null,
    'muted' => false,
])

@php
    $isEmpty = $value === null || $value === '' || $value === '—';
    $isMuted = $muted || $isEmpty;
@endphp

{{-- Read-only labelled field used across the client profile tabs. --}}
<div>
    <div class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">{{ $label }}</div>
    <div class="px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium {{ $isMuted ? 'text-[#94a3b8]' : 'text-[#0f172a]' }}">
        {{ $isEmpty ? '—' : $value }}
    </div>
</div>

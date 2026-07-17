@php
    $styles = match ($status) {
        'complete' => 'bg-[#ecfdf3] text-[#067647] border-[#a7f3d0]',
        'in_progress' => 'bg-[#eff6ff] text-[#1d4ed8] border-[#bfdbfe]',
        'needs_review' => 'bg-[#fff7ed] text-[#c2410c] border-[#fed7aa]',
        'missed' => 'bg-[#fef2f2] text-[#b91c1c] border-[#fecaca]',
        'scheduled' => 'bg-[#f1f5f9] text-[#475569] border-[#e2e8f0]',
        default => 'bg-[#f1f5f9] text-[#475569] border-[#e2e8f0]',
    };
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $styles }}">
    {{ $label }}
</span>

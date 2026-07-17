@php
    $tones = [
        'green' => 'bg-[#ecfdf5] text-[#047857] border-[#a7f3d0]',
        'orange' => 'bg-[#fff7ed] text-[#c2410c] border-[#fed7aa]',
        'amber' => 'bg-[#fffbeb] text-[#b45309] border-[#fde68a]',
        'gray' => 'bg-[#f8fafc] text-[#475569] border-[#e2e8f0]',
    ];
    $classes = $tones[$tone ?? 'gray'] ?? $tones['gray'];
@endphp
<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $classes }}">
    {{ $label }}
</span>

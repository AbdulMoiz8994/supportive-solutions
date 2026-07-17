@php
    $icons = [
        'call' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'fax' => '<path d="M6 9V2h12v7"/><rect x="6" y="13" width="12" height="8"/><path d="M6 13H4a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h2"/><path d="M18 13h2a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-2"/>',
        'sms' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'email' => '<rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/>',
        'voicemail' => '<circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>',
        'note' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
    ];
    $path = $icons[$icon ?? 'note'] ?? $icons['note'];
@endphp
<span class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-[#475569]">
    <svg class="w-3.5 h-3.5 text-[#64748b]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $path !!}</svg>
    {{ $label }}
</span>

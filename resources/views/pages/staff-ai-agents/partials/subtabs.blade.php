@props(['tab', 'agentCount', 'staffCount'])

@php
    $tabs = [
        'agents' => ['label' => 'AI Agents', 'count' => $agentCount],
        'operations' => ['label' => 'AI Operations', 'count' => null],
        'staff' => ['label' => 'Staff', 'count' => $staffCount],
    ];
@endphp

<div class="flex gap-1 border-b border-[#e2e8f0] mb-5">
    @foreach($tabs as $key => $meta)
        <a href="{{ route('staff.index', ['tab' => $key]) }}"
           class="px-4 py-2.5 text-[13.5px] font-semibold border-b-2 transition -mb-px {{ $tab === $key ? 'text-[#2563eb] border-[#2563eb]' : 'text-[#64748b] border-transparent hover:text-[#334155]' }}">
            {{ $meta['label'] }}
            @if($meta['count'] !== null)
                <span class="ml-1 text-[11px] {{ $tab === $key ? 'text-[#2563eb]' : 'text-[#94a3b8]' }}">{{ $meta['count'] }}</span>
            @endif
        </a>
    @endforeach
</div>

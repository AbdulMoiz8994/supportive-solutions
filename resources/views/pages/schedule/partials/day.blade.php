@php
    $dayEvents = $events->filter(fn ($event) => $event['date'] === today()->toDateString());
@endphp

<div class="space-y-3">
    <div class="rounded-2xl border border-[#e6eef9] bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <h2 class="text-[15px] font-bold text-[#0f172a] mb-4">{{ today()->format('l, F j, Y') }}</h2>
        @forelse ($dayEvents as $event)
            @php $cat = $categories[$event['category']] ?? $categories['compliance']; @endphp
            <div class="mb-2 flex items-center gap-3 rounded-xl border border-[#eef2f9] px-4 py-3">
                <div class="h-8 w-1 rounded-full" style="background: {{ $cat['bar'] }}"></div>
                <div class="flex-1">
                    <div class="text-[14px] font-bold text-[#0f172a]">{{ $event['title'] }}</div>
                    @if ($event['subtitle'])
                        <div class="text-[12px] text-[#64748b]">{{ $event['subtitle'] }}</div>
                    @endif
                </div>
                @if ($event['url'])
                    <a href="{{ $event['url'] }}" class="text-[12px] font-bold text-[#2563eb]">{{ $event['action_label'] }}</a>
                @endif
            </div>
        @empty
            <p class="text-[13px] text-[#64748b]">No events scheduled for today.</p>
        @endforelse
    </div>
</div>

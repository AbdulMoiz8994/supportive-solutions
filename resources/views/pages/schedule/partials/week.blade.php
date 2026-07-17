<div class="overflow-hidden rounded-2xl border border-[#e6eef9] bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
    <div class="grid grid-cols-7 border-b border-[#eef2f9] bg-[#fafcff]">
        @foreach ($weekDays as $day)
            <div class="px-3 py-3 text-center {{ $day['is_today'] ? 'bg-[#eff6ff]' : '' }}">
                <div class="text-[11px] font-bold uppercase tracking-widest text-[#94a3b8]">{{ $day['label'] }}</div>
                <div class="text-[18px] font-extrabold {{ $day['is_today'] ? 'text-[#2563eb]' : 'text-[#0f172a]' }}">{{ $day['day'] }}</div>
            </div>
        @endforeach
    </div>
    <div class="grid grid-cols-7 min-h-[420px]">
        @foreach ($weekDays as $day)
            <div class="border-r border-[#f1f5f9] p-2 space-y-2 {{ $day['is_today'] ? 'bg-[#eff6ff]/40' : '' }}">
                @foreach ($day['events'] as $event)
                    @php $cat = $categories[$event['category']] ?? $categories['compliance']; @endphp
                    <a href="{{ $event['url'] ?? '#' }}"
                       class="block rounded-lg px-2 py-2 text-[10.5px] font-semibold leading-snug"
                       style="background: {{ $cat['bg'] }}; color: {{ $cat['text'] }};">
                        {{ $event['title'] }}
                    </a>
                @endforeach

                @if (($day['overflow'] ?? 0) > 0)
                    <div class="px-1 text-[10px] font-semibold text-[#64748b]">+{{ $day['overflow'] }} more</div>
                @endif
            </div>
        @endforeach
    </div>
</div>

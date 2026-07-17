<div class="overflow-hidden rounded-2xl border border-[#e6eef9] bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
    <div class="grid grid-cols-7 border-b border-[#eef2f9] bg-[#fafcff]">
        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
            <div class="px-3 py-3 text-center text-[11px] font-bold uppercase tracking-widest text-[#94a3b8]">{{ $dayName }}</div>
        @endforeach
    </div>

    <div class="grid grid-cols-7">
        @foreach ($calendarDays as $day)
            @php
                $isToday = $day['isToday'];
            @endphp
            <div class="min-h-[118px] border-b border-r border-[#f1f5f9] p-2 {{ $day['isCurrentMonth'] ? '' : 'bg-[#fafafa]' }} {{ $isToday ? 'bg-[#eff6ff]' : '' }}">
                <div class="mb-2 flex items-center justify-between px-0.5">
                    <span class="text-[13px] font-bold {{ $day['isCurrentMonth'] ? 'text-[#0f172a]' : 'text-[#cbd5e1]' }}">{{ $day['day'] }}</span>
                    @if ($isToday)
                        <span class="rounded-full bg-[#2563eb] px-2 py-0.5 text-[10px] font-bold text-white">Today</span>
                    @endif
                </div>

                <div class="space-y-1">
                    @foreach ($day['events'] as $event)
                        @php
                            $cat = $categories[$event['category']] ?? $categories['compliance'];
                            $showPerson = $event['person_name']
                                && ! str_contains(strtolower($event['title']), strtolower($event['person_name']));
                        @endphp
                        <a href="{{ $event['url'] ?? '#' }}"
                           class="block truncate rounded-md px-2 py-1 text-[10.5px] font-semibold leading-tight hover:opacity-90"
                           style="background: {{ $cat['bg'] }}; color: {{ $cat['text'] }};">
                            {{ $event['title'] }}
                            @if ($showPerson)
                                <span class="opacity-80">— {{ $event['person_name'] }}</span>
                            @endif
                        </a>
                    @endforeach

                    @if ($day['overflow'] > 0)
                        <div class="px-1 text-[10px] font-semibold text-[#64748b]">+{{ $day['overflow'] }} more</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

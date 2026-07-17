<div class="space-y-5">
    @forelse ($agendaGroups as $group)
        <div>
            <div class="mb-3 flex items-center gap-2">
                <h2 class="text-[14px] font-bold text-[#0f172a]">{{ $group['label'] }}</h2>
                @if ($group['is_today'])
                    <span class="rounded-full bg-[#2563eb] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Today</span>
                @endif
            </div>

            <div class="space-y-2.5">
                @foreach ($group['events'] as $event)
                    @php $cat = $categories[$event['category']] ?? $categories['compliance']; @endphp
                    <div class="flex items-stretch overflow-hidden rounded-xl border border-[#e6eef9] bg-white shadow-[0_1px_2px_rgba(15,23,42,0.03)]">
                        <div class="w-1.5 shrink-0" style="background: {{ $cat['bar'] }}"></div>
                        <div class="flex flex-1 items-center gap-3 px-4 py-3.5">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background: {{ $cat['bg'] }}; color: {{ $cat['text'] }};">
                                @include('pages.schedule.partials.icon', ['icon' => $event['icon']])
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-[14px] font-bold text-[#0f172a] truncate">{{ $event['title'] }}</div>
                                @if ($event['subtitle'])
                                    <div class="text-[12.5px] text-[#64748b] truncate">{{ $event['subtitle'] }}</div>
                                @endif
                            </div>
                            <div class="hidden sm:flex items-center gap-3 shrink-0">
                                @if ($event['needs_you'])
                                    <span class="rounded-full border border-[#fdecc8] bg-[#fff8eb] px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-[#b54708]">Needs you</span>
                                @else
                                    <span class="rounded-full border border-[#d1fadf] bg-[#ecfdf3] px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-[#067647]">Auto</span>
                                @endif
                                @if ($event['url'])
                                    <a href="{{ $event['url'] }}" class="inline-flex items-center gap-1 text-[12.5px] font-bold text-[#2563eb] hover:text-[#1d4ed8]">
                                        {{ $event['action_label'] }}
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-[#e6eef9] bg-white px-6 py-12 text-center text-[13px] text-[#64748b]">
            No upcoming events in the next 30 days.
        </div>
    @endforelse
</div>

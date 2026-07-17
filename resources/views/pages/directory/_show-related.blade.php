@if (! empty($showProfile['related_links']))
    <section class="rounded-2xl border border-[#e2e8f0] bg-white p-5">
        <h2 class="mb-4 text-[11px] font-bold uppercase tracking-wider text-[#64748b]">Related</h2>
        <ul class="space-y-3">
            @foreach ($showProfile['related_links'] as $link)
                <li class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#f8fafc] text-[#64748b]">
                            @include('pages.directory._show-icon', ['name' => $link['icon'], 'class' => 'h-3.5 w-3.5'])
                        </span>
                        <span class="text-[12.5px] font-semibold text-[#334155]">{{ $link['label'] }}</span>
                    </div>
                    @if ($link['href'])
                        <a href="{{ $link['href'] }}" class="shrink-0 text-[12px] font-semibold text-[#2563eb] hover:text-[#1d4ed8] hover:underline">{{ $link['suffix'] }}</a>
                    @elseif (! empty($link['muted']))
                        <span class="max-w-[140px] truncate text-right text-[11px] text-[#94a3b8]">{{ $link['muted'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif

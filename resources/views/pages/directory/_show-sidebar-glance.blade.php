<section class="rounded-[10px] border border-[#e2e8f0] bg-white p-4">
    <h2 class="mb-2.5 text-[12px] font-semibold uppercase tracking-wide text-[#64748b]">{{ $showProfile['glance_title'] }}</h2>
    <dl class="divide-y divide-[#f1f5f9] text-[12.5px]">
        @foreach ($showProfile['glance_rows'] as $row)
            <div class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                <dt class="text-[#64748b]">{{ $row['label'] }}</dt>
                <dd class="text-right font-bold text-[#0f172a]">
                    @if (! empty($row['pill']))
                        <span class="rounded-md border border-[#e2e8f0] bg-[#f1f5f9] px-2 py-0.5 text-[12px] font-bold text-[#0f172a]">{{ $row['value'] }}</span>
                    @elseif (! empty($row['badge_variant']))
                        <x-ui.pill :variant="$row['badge_variant']" size="xs">{{ $row['value'] }}</x-ui.pill>
                    @else
                        {{ $row['value'] }}
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
</section>

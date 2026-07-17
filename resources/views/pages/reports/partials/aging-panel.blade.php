@props(['title', 'subtitle', 'buckets', 'footnote' => null, 'link' => null, 'linkLabel' => null])

@php
    $total = max(array_sum(array_column($buckets, 'amount')), 1);
    $colors = ['current' => 'bg-[#10b981]', '31_60' => 'bg-[#f59e0b]', '61_90' => 'bg-[#f97316]', '90_plus' => 'bg-[#ef4444]'];
    $labels = ['current' => '0–30 days', '31_60' => '31–60 days', '61_90' => '61–90 days', '90_plus' => '90+ days'];
@endphp

<div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
    <h3 class="text-[14px] font-semibold text-[#0f172a]">{{ $title }}</h3>
    <p class="text-[12px] text-[#94a3b8] mt-0.5 mb-4">{{ $subtitle }}</p>
    @foreach($buckets as $key => $bucket)
        <div class="flex items-center gap-2.5 mb-2.5 text-[12px]">
            <span class="w-20 text-[#64748b] shrink-0">{{ $labels[$key] ?? $key }}</span>
            <div class="flex-1 h-3.5 bg-[#f1f5f9] rounded-full overflow-hidden">
                <div class="{{ $colors[$key] ?? 'bg-[#2563eb]' }} h-full rounded-full" style="width: {{ max(round(($bucket['amount'] / $total) * 100), $bucket['amount'] > 0 ? 2 : 0) }}%"></div>
            </div>
            <span class="w-20 text-right font-bold text-[#0f172a] shrink-0">${{ number_format($bucket['amount']) }}</span>
        </div>
    @endforeach
    @if($footnote || $link)
        <div class="flex items-center justify-between mt-3 text-[11.5px] text-[#94a3b8]">
            <span>{{ $footnote }}</span>
            @if($link)
                <a href="{{ $link }}" class="text-[#2563eb] font-semibold hover:underline">{{ $linkLabel }}</a>
            @endif
        </div>
    @endif
</div>

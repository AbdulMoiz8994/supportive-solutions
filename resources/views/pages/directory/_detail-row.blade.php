@props(['label', 'value' => null, 'href' => null, 'copyable' => false, 'multiline' => false, 'linkSuffix' => null, 'inline' => false])

@if ($inline)
    <dt class="text-[13px] text-[#94a3b8]">{{ $label }}</dt>
    <dd class="text-[13px] font-medium text-[#0f172a]">
@else
<div class="grid grid-cols-1 gap-1 sm:grid-cols-[minmax(120px,160px)_1fr] sm:gap-4">
    <dt class="text-[12px] font-semibold text-[#64748b]">{{ $label }}</dt>
    <dd class="text-[12.5px] font-medium text-[#0f172a]">
@endif
        @if ($href && $value)
            <a href="{{ $href }}" class="font-semibold text-[#2563eb] hover:underline focus:outline-none focus:ring-2 focus:ring-[#2563eb]/20 rounded">{{ $value }}{{ $linkSuffix }}</a>
        @elseif ($href && ! $value)
            <a href="{{ $href }}" class="font-semibold text-[#2563eb] hover:underline focus:outline-none focus:ring-2 focus:ring-[#2563eb]/20 rounded">{{ $label }}{{ $linkSuffix }}</a>
        @elseif ($multiline && $value)
            <p class="whitespace-pre-wrap leading-relaxed text-[#475569]">{{ $value }}</p>
        @elseif ($value)
            <span class="inline-flex items-center gap-2">
                <span>{{ $value }}</span>
                @if ($copyable)
                    <button type="button" onclick="navigator.clipboard.writeText(@js($value))"
                            class="rounded border border-[#e2e8f0] px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#2563eb] hover:bg-[#eff6ff]"
                            aria-label="Copy {{ $label }}">Copy</button>
                @endif
            </span>
        @else
            <span class="text-[#94a3b8]">—</span>
        @endif
    </dd>
@if (! $inline)
</div>
@endif

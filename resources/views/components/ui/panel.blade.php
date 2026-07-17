@props([
    'title' => null,
    'subtitle' => null,
    'link' => null,
    'linkLabel' => null,
    'bodyClass' => '',
])

{{-- Sky-blue surface card (#EEF4FB) with an optional header (title + right-aligned
     action link). Padding is a consistent 20px on every side; when a custom
     `bodyClass` is supplied the outer padding is dropped so tables/edge-to-edge
     content can manage their own spacing. --}}
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-card-border bg-card '.($bodyClass !== '' ? '' : 'p-5')]) }}>
    @if($title || $linkLabel)
        <div class="flex items-center justify-between {{ $bodyClass !== '' ? 'px-5 pt-5 pb-3' : 'mb-4' }}">
            <div>
                @if($title)<h3 class="text-base font-bold text-[#0f172a] leading-tight">{{ $title }}</h3>@endif
                @if($subtitle)<p class="text-sm font-medium text-[#94a3b8] mt-0.5">{{ $subtitle }}</p>@endif
            </div>
            @if($linkLabel)
                <a href="{{ $link ?? '#' }}" class="text-sm font-semibold text-[#2563eb] hover:text-[#1d4ed8] transition-colors whitespace-nowrap inline-flex items-center gap-0.5">
                    {{ $linkLabel }} <span class="text-sm">&rsaquo;</span>
                </a>
            @endif
        </div>
    @endif
    <div class="{{ $bodyClass !== '' ? $bodyClass : '' }}">
        {{ $slot }}
    </div>
</div>

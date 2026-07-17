@props([
    'title' => null,
    'subtitle' => null,
    'class' => '',
    'errorPrefixes' => [],
    'errorKeys' => [],
])

<div {{ $attributes->merge(['class' => trim(($settingsCard ?? 'rounded-2xl border border-[#e2e8f0] bg-white shadow-sm').' p-6 '.$class)]) }}>
    @if($title)
        <h3 class="{{ $settingsSectionTitle ?? 'text-xl font-black text-[#1e293b] tracking-tighter' }}">{{ $title }}</h3>
        @if($subtitle)
            <p class="{{ $settingsSectionDesc ?? 'text-sm text-[#64748b] mt-1.5 font-bold opacity-70' }} mb-5">{{ $subtitle }}</p>
        @else
            <div class="mb-5"></div>
        @endif
    @endif

    @if(filled($errorPrefixes) || filled($errorKeys))
        <x-global-settings.validation-errors
            :prefixes="$errorPrefixes"
            :keys="$errorKeys"
            class="{{ $title ? 'mb-4' : 'mb-4' }}"
        />
    @endif

    {{ $slot }}
</div>

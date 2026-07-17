@php
    $title = $title ?? '';
    $description = $description ?? null;
@endphp
<div class="mb-5 sm:mb-6">
    <h2 class="{{ $settingsSectionTitle ?? 'text-lg font-bold text-[#0f172a]' }}">{{ $title }}</h2>
    @if($description)
        <p class="{{ $settingsSectionDesc ?? 'text-sm text-[#64748b] mt-1' }}">{{ $description }}</p>
    @endif
</div>

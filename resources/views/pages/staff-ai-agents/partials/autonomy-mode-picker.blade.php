@props([
    'modes',
    'name' => 'autonomy_mode',
    'value' => 'approval_required',
])

@php
    $selected = old($name, $value);
@endphp

<div class="inline-flex border border-[#e2e8f0] rounded-lg overflow-hidden" x-data="{ autonomy: @js($selected) }">
    @foreach($modes as $mode => $label)
        <label class="cursor-pointer">
            <input type="radio" name="{{ $name }}" value="{{ $mode }}" x-model="autonomy" class="sr-only">
            <span class="block px-3 py-1.5 text-[12px] font-semibold border-r border-[#e2e8f0] last:border-r-0 transition whitespace-nowrap"
                  :class="autonomy === @js($mode) ? 'bg-[#2563eb] text-white' : 'bg-white text-[#475569] hover:bg-[#f8fafc]'">
                {{ $label }}
            </span>
        </label>
    @endforeach
</div>
@error($name)
    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
@enderror

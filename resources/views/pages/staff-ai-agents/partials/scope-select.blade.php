@props([
    'name',
    'label',
    'hint' => null,
    'options' => [],
    'selected' => [],
    'placeholder' => 'Select…',
])

<div>
    <p class="text-[12px] font-semibold text-[#0f172a] mb-1">
        {{ $label }}
        @if($hint)
            <span class="text-[#94a3b8] font-normal">{{ $hint }}</span>
        @endif
    </p>
    <select name="{{ $name }}[]" multiple
            class="js-select2-multi w-full"
            data-placeholder="{{ $placeholder }}">
        @foreach($options as $value => $text)
            <option value="{{ $value }}" @selected(collect($selected)->contains($value))>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
    @enderror
    @error($name.'.*')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

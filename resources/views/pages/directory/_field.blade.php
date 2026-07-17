@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'placeholder' => null,
    'help' => null,
    'maxlength' => null,
    'rows' => 4,
    'options' => [],
    'colSpan' => 1,
])

@php
    $inputId = 'directory-field-'.$name;
    $hasError = $errors->has($name);
    $inputClasses = 'w-full rounded-xl border bg-white px-3.5 py-2.5 text-[13px] font-medium text-[#0f172a] outline-none transition focus:ring-2 focus:ring-[#2563eb]/20 '
        .($hasError ? 'border-[#fca5a5] focus:border-[#ef4444]' : 'border-[#e2e8f0] focus:border-[#2563eb]');
    $colClass = $colSpan === 2 ? 'md:col-span-2' : '';
@endphp

<div class="{{ $colClass }}">
    <label for="{{ $inputId }}" class="mb-1.5 block text-[12px] font-bold text-[#0f172a]">
        {{ $label }}@if ($required)<span class="text-[#ef4444]" aria-hidden="true"> *</span><span class="sr-only">(required)</span>@endif
    </label>

    @if ($type === 'select')
        <select id="{{ $inputId }}" name="{{ $name }}" @if ($required) required aria-required="true" @endif @if ($hasError) aria-invalid="true" @endif class="{{ $inputClasses }}">
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea id="{{ $inputId }}" name="{{ $name }}" rows="{{ $rows }}" @if ($required) required @endif @if ($maxlength) maxlength="{{ $maxlength }}" data-maxlength="{{ $maxlength }}" @endif @if ($placeholder) placeholder="{{ $placeholder }}" @endif class="{{ $inputClasses }}" oninput="if (this.dataset.maxlength) { const el = document.getElementById('{{ $inputId }}-counter'); if (el) el.textContent = this.value.length + ' / ' + this.dataset.maxlength; }">{{ old($name, $value) }}</textarea>
        @if ($maxlength)<p id="{{ $inputId }}-counter" class="mt-1 text-right text-[11px] text-[#94a3b8]">{{ strlen(old($name, $value ?? '')) }} / {{ $maxlength }}</p>@endif
    @else
        <input type="{{ $type }}" id="{{ $inputId }}" name="{{ $name }}" value="{{ old($name, $value) }}" @if ($required) required @endif @if ($maxlength) maxlength="{{ $maxlength }}" @endif @if ($placeholder) placeholder="{{ $placeholder }}" @endif @if ($hasError) aria-invalid="true" @endif class="{{ $inputClasses }}">
    @endif

    @if ($help)<p class="mt-1 text-[11px] text-[#94a3b8]">{{ $help }}</p>@endif
    @error($name)<p class="mt-1 text-[11px] font-semibold text-[#ef4444]" role="alert">{{ $message }}</p>@enderror
</div>

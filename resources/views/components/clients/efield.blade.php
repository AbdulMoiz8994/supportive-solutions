@props([
    'label' => '',
    'name' => null,          // input name; omit for interactive-only (not persisted yet)
    'value' => null,         // current value (also used as the read display)
    'type' => 'text',        // text | email | tel | number | date | textarea | select
    'options' => [],         // select options: [value => label] or [label, label, ...]
    'selected' => null,      // selected option value (defaults to $value)
    'placeholder' => '',
    'muted' => false,
    'dropdown' => false,     // shows the "⌄ dropdown" hint + chevron (per Figma)
    'required' => false,
    'col' => 1,              // 1 | 2  (column span inside a 2-col grid)
    'rows' => 3,
    'display' => null,       // static read-only display (e.g. masked SSN); disables live text
])

@php
    // Value bound to the input — keeps the user's entry across validation errors.
    $bound = $name ? old($name, $value) : $value;
    $selectedVal = $name ? old($name, $selected ?? $value) : ($selected ?? $value);

    // Normalise select options to an associative [value => label] map.
    $optsAssoc = [];
    foreach ($options as $k => $v) {
        $optsAssoc[is_int($k) ? $v : $k] = $v;
    }

    $hasError = $name && $errors->has($name);
    $inputBase = 'w-full px-3.5 py-2.5 rounded-[9px] border bg-white text-sm font-medium text-[#0f172a] outline-none transition focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 placeholder-[#94a3b8] '
        . ($hasError ? 'border-[#fda29b]' : 'border-card-border');

    // x-data for the field. Built with Js::from so it is valid, attribute-safe JS
    // (note: the @js Blade directive does not run inside a @php block).
    $valInit = (string) ($type === 'select' ? ($selectedVal ?? '') : ($bound ?? ''));
    $xdata = ['val' => $valInit, 'orig' => ''];
    if ($type === 'select') {
        $xdata['labels'] = $optsAssoc ?: new \stdClass;
    }

    // Cancel reverts to the original value; an interactive 'save' commits it.
    // Both are scoped to the parent edit-panel via its `panelId`.
    $fieldInit = "orig = val;"
        . " if (typeof panelId !== 'undefined') {"
        . "   window.addEventListener('cl-cancel-' + panelId, () => { val = orig });"
        . "   window.addEventListener('cl-commit-' + panelId, () => { orig = val });"
        . " }";
@endphp

<div x-data="{{ \Illuminate\Support\Js::from($xdata) }}" x-init="{!! $fieldInit !!}" @class(['col-span-2' => $col == 2])>
    <div class="flex items-center justify-between mb-1.5">
        <span class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide">{{ $label }}@if($required)<span class="text-[#ef4444]"> *</span>@endif</span>
        @if($dropdown)
            <span class="text-xs font-semibold text-[#94a3b8] inline-flex items-center gap-0.5 normal-case tracking-normal">
                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>dropdown
            </span>
        @endif
    </div>

    {{-- Read-only display --}}
    <div x-show="!editing"
        class="px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium {{ ($muted && $display !== null) ? 'text-[#94a3b8]' : '' }}"
        @if($display === null) :class="(val === '' || val === null) ? 'text-[#94a3b8]' : 'text-[#0f172a]'" @endif>
        @if($display !== null)
            {{ $display === '' ? '—' : $display }}
        @elseif($type === 'select')
            <span x-text="(labels[val] ?? val) || '—'"></span>
        @else
            <span x-text="val || '—'"></span>
        @endif
    </div>

    {{-- Edit control --}}
    <div x-show="editing" x-cloak>
        @if($type === 'textarea')
            <textarea
                x-model="val"
                @if($name) name="{{ $name }}" @endif
                rows="{{ $rows }}"
                placeholder="{{ $placeholder }}"
                @if($required) required @endif
                class="{{ $inputBase }}"></textarea>
        @elseif($type === 'select')
            <div class="relative">
                <select
                    x-model="val"
                    @if($name) name="{{ $name }}" @endif
                    @if($required) required @endif
                    class="{{ $inputBase }} appearance-none pr-9 cursor-pointer">
                    @if($placeholder)<option value="">{{ $placeholder }}</option>@endif
                    @foreach($optsAssoc as $val => $lbl)
                        <option value="{{ $val }}">{{ $lbl }}</option>
                    @endforeach
                </select>
                <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
        @else
            <input
                type="{{ $type }}"
                x-model="val"
                @if($name) name="{{ $name }}" @endif
                placeholder="{{ $placeholder }}"
                @if($required) required @endif
                {{ $attributes }}
                class="{{ $inputBase }}">
        @endif

        @if($name)
            @error($name)
                <p class="mt-1 text-xs font-medium text-[#d92d20]">{{ $message }}</p>
            @enderror
        @endif
    </div>
</div>

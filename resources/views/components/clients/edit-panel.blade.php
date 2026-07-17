@props([
    'title' => null,
    'action' => null,        // form action; null = interactive-only (not persisted yet)
    'method' => 'PUT',
    'section' => null,        // unique id; re-opens this panel after a validation error
    'tab' => 'demographics',  // tab to return to after a save
    'editLabel' => 'Edit',
])

@php
    $pid = 'ep_'.\Illuminate\Support\Str::random(8);
    // Re-open this panel automatically if its save bounced back with errors.
    $openOnError = ($section !== null && old('section') === $section && $errors->any()) ? 'true' : 'false';
@endphp

<div x-data="{ editing: {{ $openOnError }}, panelId: '{{ $pid }}' }"
     {{ $attributes->merge(['class' => 'rounded-2xl border border-card-border bg-card p-5']) }}>
    <form
        @if($action) method="POST" action="{{ $action }}" @else @submit.prevent="editing = false; window.dispatchEvent(new CustomEvent('cl-commit-' + panelId))" @endif
        x-ref="form">
        @if($action)
            @csrf
            @method($method)
            <input type="hidden" name="section" value="{{ $section }}">
            <input type="hidden" name="tab" value="{{ $tab }}">
        @endif

        {{-- Header --}}
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-[#0f172a] leading-tight">{{ $title }}</h3>

            <button type="button" x-show="!editing" @click="editing = true"
                class="inline-flex items-center gap-1 text-sm font-semibold text-[#2563eb] hover:text-[#1d4ed8] transition-colors">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                {{ $editLabel }}
            </button>

            <span x-show="editing" x-cloak class="inline-flex items-center gap-1 text-sm font-semibold text-[#2563eb]">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                Editing
            </span>
        </div>

        {{-- Fields --}}
        {{ $slot }}

        {{-- Footer --}}
        <div x-show="editing" x-cloak class="flex items-center justify-end gap-2.5 mt-5 pt-4 border-t border-card-border">
            <button type="button"
                @click="editing = false; window.dispatchEvent(new CustomEvent('cl-cancel-' + panelId))"
                class="inline-flex items-center justify-center font-semibold rounded-[9px] text-sm px-4 py-2 bg-white text-[#475569] border border-[#d8e2f0] hover:border-[#94a3b8] hover:text-[#1e293b] transition-all">
                Cancel
            </button>
            <button type="submit"
                class="inline-flex items-center justify-center font-semibold rounded-[9px] text-sm px-4 py-2 bg-[#2563eb] text-white border border-[#2563eb] hover:bg-[#1d4ed8] shadow-[0_2px_8px_rgba(37,99,235,0.25)] transition-all">
                Save
            </button>
        </div>
    </form>
</div>

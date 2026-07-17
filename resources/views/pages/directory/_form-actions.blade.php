@props(['submitLabel' => 'Save Contact', 'cancelUrl'])

<div class="flex flex-col gap-3 border-t border-[#eef2f9] pt-6 sm:flex-row sm:items-center">
    <button type="submit"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#2563eb] px-5 py-2.5 text-[12px] font-semibold text-white shadow-sm transition hover:bg-[#1d4ed8] focus:outline-none focus:ring-2 focus:ring-[#2563eb]/30 disabled:cursor-not-allowed disabled:opacity-60"
            x-bind:disabled="submitting" x-bind:aria-busy="submitting">
        <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <span x-text="submitting ? 'Saving…' : '{{ $submitLabel }}'">{{ $submitLabel }}</span>
    </button>
    <a href="{{ $cancelUrl }}" class="inline-flex items-center justify-center rounded-xl border border-[#e2e8f0] bg-white px-5 py-2.5 text-[12px] font-semibold text-[#475569] transition hover:bg-[#f8fafc]">Cancel</a>
</div>

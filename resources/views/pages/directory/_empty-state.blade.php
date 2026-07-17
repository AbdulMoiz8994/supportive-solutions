@props(['hasFilters' => false])

<div class="mx-auto max-w-sm text-center py-4">
    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#f1f5f9] text-[#94a3b8]">
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 0 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/></svg>
    </div>
    <h3 class="text-[15px] font-bold text-[#0f172a]">{{ $hasFilters ? 'No matching contacts' : 'No directory contacts yet' }}</h3>
    <p class="mt-1.5 text-[12px] text-[#64748b]">
        {{ $hasFilters ? 'Try adjusting your search or filters.' : 'Add physicians, coordinators, vendors, and partners to get started.' }}
    </p>
    <div class="mt-4 flex flex-wrap justify-center gap-2">
        @if ($hasFilters)
            <a href="{{ route('directory') }}" class="rounded-xl border border-[#e2e8f0] bg-white px-4 py-2 text-[12px] font-semibold text-[#475569] hover:bg-[#f8fafc]">Clear filters</a>
        @endif
        <a href="{{ route('directory.create') }}" class="rounded-xl bg-[#2563eb] px-4 py-2 text-[12px] font-semibold text-white hover:bg-[#1d4ed8]">Add entry</a>
    </div>
</div>
